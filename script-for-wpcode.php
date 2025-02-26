<?php
/**
 * Скрипт для WPCode - соотнесение вариаций товаров с цветами и изображениями
 * 
 * Этот скрипт позволяет автоматически:
 * 1. Соотносить артикулы вариаций с номерами цветов из файла соответствий
 * 2. Устанавливать атрибут "color" для вариаций
 * 3. Присваивать соответствующие фотографии к вариациям на основе номера цвета и названия модели
 */

// Путь к файлу соответствий - замените на реальный путь в вашей системе
$correspondence_file = ABSPATH . 'wp-content/uploads/correspondence/соответствия.txt';

// Путь к папке с изображениями - замените на реальный путь в вашей системе
$images_directory = ABSPATH . 'wp-content/uploads/product_images/';

// Логирование для отслеживания работы скрипта
function write_log($message, $logfile = 'color-matcher-log.txt') {
    $time = date('[Y-m-d H:i:s]');
    $logpath = ABSPATH . $logfile;
    file_put_contents($logpath, $time . ' ' . $message . "\n", FILE_APPEND);
}

/**
 * Основная функция для обработки соответствий и обновления атрибутов вариаций
 */
function update_product_variations_with_colors() {
    global $correspondence_file, $images_directory;
    
    // Проверяем, существует ли файл соответствий
    if (!file_exists($correspondence_file)) {
        write_log('Файл соответствий не найден: ' . $correspondence_file);
        return 'Файл соответствий не найден: ' . $correspondence_file;
    }
    
    // Считываем содержимое файла
    $file_content = file_get_contents($correspondence_file);
    $lines = explode("\n", $file_content);
    
    $current_product = '';
    $log = [];
    $processed_count = 0;
    
    // Создаем массив соответствий
    $sku_to_color = [];
    foreach ($lines as $line_number => $line) {
        $line = trim($line);
        
        // Пропускаем пустые строки
        if (empty($line)) {
            continue;
        }
        
        // Проверяем, содержит ли строка артикул и цвет
        if (preg_match('/^([a-zA-Z0-9-]+)\s+(.+)$/', $line, $matches)) {
            $sku = $matches[1]; // Артикул
            $color_info = $matches[2]; // Информация о цвете
            
            // Сохраняем соответствие артикула и цвета
            $sku_to_color[$sku] = $color_info;
        } else {
            // Это строка с названием продукта или другая информация
            $current_product = $line;
        }
    }
    
    // Обрабатываем все продукты в магазине
    $products = wc_get_products([
        'type' => 'variable',
        'limit' => -1,
    ]);
    
    foreach ($products as $product) {
        $product_id = $product->get_id();
        $product_name = $product->get_name();
        
        // Получаем все вариации продукта
        $variations = $product->get_available_variations();
        
        foreach ($variations as $variation_data) {
            $variation_id = $variation_data['variation_id'];
            $variation = wc_get_product($variation_id);
            
            if (!$variation) {
                continue;
            }
            
            // Получаем артикул вариации
            $sku = $variation->get_sku();
            
            if (empty($sku) || !isset($sku_to_color[$sku])) {
                continue;
            }
            
            // Получаем информацию о цвете
            $color_info = $sku_to_color[$sku];
            
            // Обновляем атрибут color у вариации
            $color_update_result = update_variation_color($variation_id, $color_info);
            
            if (!$color_update_result) {
                $log[] = "Не удалось обновить цвет для вариации {$sku} (ID: {$variation_id})";
                write_log("Не удалось обновить цвет для вариации {$sku} (ID: {$variation_id})");
                continue;
            }
            
            // Извлекаем название модели из названия продукта для поиска фото
            $model_name = extract_model_name($product_name);
            
            // Извлекаем номер цвета из информации о цвете
            $color_number = extract_color_number($color_info);
            
            if ($color_number) {
                // Ищем подходящее изображение
                $image_path = find_image_by_model_and_color($images_directory, $model_name, $color_number);
                
                if ($image_path) {
                    // Присваиваем изображение вариации
                    $image_result = assign_image_to_variation($variation_id, $image_path);
                    
                    if ($image_result) {
                        $log[] = "Успешно: Вариация {$sku} (ID: {$variation_id}), цвет: {$color_info}, изображение: " . basename($image_path);
                        write_log("Успешно: Вариация {$sku} (ID: {$variation_id}), цвет: {$color_info}, изображение: " . basename($image_path));
                        $processed_count++;
                    } else {
                        $log[] = "Ошибка: Не удалось присвоить изображение для вариации {$sku} (ID: {$variation_id})";
                        write_log("Ошибка: Не удалось присвоить изображение для вариации {$sku} (ID: {$variation_id})");
                    }
                } else {
                    $log[] = "Изображение для модели '{$model_name}' и цвета '{$color_number}' не найдено";
                    write_log("Изображение для модели '{$model_name}' и цвета '{$color_number}' не найдено");
                }
            } else {
                $log[] = "Не удалось извлечь номер цвета из '{$color_info}' для вариации {$sku}";
                write_log("Не удалось извлечь номер цвета из '{$color_info}' для вариации {$sku}");
            }
        }
    }
    
    $log[] = "Обработка завершена. Успешно обработано: {$processed_count} вариаций.";
    write_log("Обработка завершена. Успешно обработано: {$processed_count} вариаций.");
    
    return implode("\n", $log);
}

/**
 * Извлекает название модели из полного названия продукта
 */
function extract_model_name($product_name) {
    // Ищем "Drops X", где X - название модели
    if (preg_match('/Drops\s+([^,\s]+)/', $product_name, $matches)) {
        return $matches[1];
    }
    
    // Если не нашли, пробуем альтернативный способ
    // Предполагаем, что первое слово - это бренд
    $parts = explode(' ', $product_name, 2);
    return isset($parts[1]) ? $parts[1] : $product_name;
}

/**
 * Извлекает номер цвета из строки с информацией о цвете
 */
function extract_color_number($color_info) {
    // Пытаемся найти формат "XX / название цвета"
    if (preg_match('/^(\d+)\s*\//', $color_info, $matches)) {
        return $matches[1];
    }
    
    // Пытаемся найти просто числовой код в начале строки
    if (preg_match('/^(\d+)/', $color_info, $matches)) {
        return $matches[1];
    }
    
    // Если цвет не содержит номера, используем сам цвет как идентификатор
    return null;
}

/**
 * Обновляет атрибут color для вариации товара
 */
function update_variation_color($variation_id, $color_value) {
    // Получаем объект вариации
    $variation = wc_get_product($variation_id);
    
    if (!$variation || !is_object($variation)) {
        return false;
    }
    
    // Получаем родительский товар
    $parent_id = $variation->get_parent_id();
    $parent = wc_get_product($parent_id);
    
    if (!$parent || !is_object($parent)) {
        return false;
    }
    
    // Определяем таксономию для цвета (обычно pa_color)
    $color_taxonomy = 'pa_color';
    
    // Проверяем, существует ли таксономия
    if (!taxonomy_exists($color_taxonomy)) {
        register_taxonomy(
            $color_taxonomy,
            'product',
            array(
                'hierarchical' => false,
                'label' => 'Color',
                'query_var' => true,
                'rewrite' => array('slug' => 'color')
            )
        );
    }
    
    // Проверяем, существует ли термин с таким значением
    $term = get_term_by('name', $color_value, $color_taxonomy);
    
    if (!$term) {
        // Создаем новый термин
        $term = wp_insert_term($color_value, $color_taxonomy);
        
        if (is_wp_error($term)) {
            return false;
        }
        
        $term_id = $term['term_id'];
        $term_slug = get_term($term_id, $color_taxonomy)->slug;
    } else {
        $term_id = $term->term_id;
        $term_slug = $term->slug;
    }
    
    // Обновляем атрибут у вариации
    update_post_meta($variation_id, "attribute_{$color_taxonomy}", $term_slug);
    
    // Добавляем термин к родительскому товару
    wp_set_object_terms($parent_id, $term_id, $color_taxonomy, true);
    
    // Сохраняем изменения в родительском товаре
    update_post_meta($parent_id, '_product_attributes', get_post_meta($parent_id, '_product_attributes', true));
    
    return true;
}

/**
 * Ищет изображение по модели и номеру цвета
 */
function find_image_by_model_and_color($images_directory, $model_name, $color_number) {
    // Проверяем, существует ли корневая директория
    if (!is_dir($images_directory)) {
        write_log("Директория с изображениями не найдена: {$images_directory}");
        return false;
    }
    
    // Ищем папку, содержащую название модели (не чувствительно к регистру)
    $model_dir = null;
    $dirs = glob($images_directory . '*', GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        $dir_name = basename($dir);
        
        // Проверяем, содержит ли имя папки название модели
        if (stripos($dir_name, $model_name) !== false) {
            $model_dir = $dir . '/';
            break;
        }
    }
    
    if (!$model_dir) {
        write_log("Директория для модели '{$model_name}' не найдена");
        return false;
    }
    
    // Ищем файлы с номером цвета в имени
    // Это могут быть файлы вида 01.jpg, 01-2.jpg и т.д.
    $pattern = $model_dir . $color_number . '*.*';
    $images = glob($pattern);
    
    if (empty($images)) {
        write_log("Изображения для модели '{$model_name}' и цвета '{$color_number}' не найдены по шаблону: {$pattern}");
        return false;
    }
    
    // Если есть несколько изображений, выбираем наименьшее по размеру
    if (count($images) > 1) {
        $smallest_image = null;
        $smallest_size = PHP_INT_MAX;
        
        foreach ($images as $image) {
            $size = filesize($image);
            
            if ($size < $smallest_size) {
                $smallest_size = $size;
                $smallest_image = $image;
            }
        }
        
        return $smallest_image;
    }
    
    // Возвращаем единственное найденное изображение
    return $images[0];
}

/**
 * Присваивает изображение вариации товара
 */
function assign_image_to_variation($variation_id, $image_path) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    
    // Проверка, существует ли файл
    if (!file_exists($image_path)) {
        write_log("Файл изображения не существует: {$image_path}");
        return false;
    }
    
    // Копируем файл во временную директорию
    $temp_file = wp_tempnam(basename($image_path));
    if (!copy($image_path, $temp_file)) {
        write_log("Не удалось скопировать изображение во временный файл: {$image_path}");
        return false;
    }
    
    // Подготавливаем файл для загрузки
    $file_array = array(
        'name' => basename($image_path),
        'tmp_name' => $temp_file
    );
    
    // Добавляем изображение в медиабиблиотеку
    $attachment_id = media_handle_sideload($file_array, $variation_id);
    
    // Удаляем временный файл
    @unlink($temp_file);
    
    if (is_wp_error($attachment_id)) {
        write_log("Ошибка при загрузке изображения: " . $attachment_id->get_error_message());
        return false;
    }
    
    // Устанавливаем изображение как основное для вариации
    update_post_meta($variation_id, '_thumbnail_id', $attachment_id);
    
    return true;
}

// Запускаем обработку и выводим результаты
function run_color_matcher() {
    $results = update_product_variations_with_colors();
    echo "<pre>{$results}</pre>";
}

// Для запуска в WPCode, раскомментируйте следующую строку
// run_color_matcher();
