<?php
/**
 * Скрипт для соотнесения вариаций товаров с цветами и фотографиями
 */

// Основная функция для обработки
function update_product_variations_with_colors() {
    // Путь к файлу соответствий (замените на ваш путь)
    $correspondence_file = ABSPATH . 'path/to/your/correspondence.txt';
    
    // Путь к папке с изображениями (замените на ваш путь)
    $images_directory = ABSPATH . 'path/to/photos/';
    
    // Проверяем, существует ли файл
    if (!file_exists($correspondence_file)) {
        return 'Файл соответствий не найден: ' . $correspondence_file;
    }
    
    // Считываем содержимое файла
    $file_content = file_get_contents($correspondence_file);
    $lines = explode("\n", $file_content);
    
    $current_product = '';
    $log = [];
    $processed_count = 0;
    
    // Обрабатываем каждую строку
    foreach ($lines as $line_number => $line) {
        $line = trim($line);
        
        // Пропускаем пустые строки
        if (empty($line)) {
            continue;
        }
        
        // Определяем, является ли строка названием продукта или записью с артикулом/цветом
        // Запись с артикулом и цветом обычно содержит артикул и номер цвета со слэшем
        if (preg_match('/^([a-zA-Z0-9]+)\s+(\d+)\s+\//', $line, $matches)) {
            // Это строка с артикулом и цветом
            $sku = $matches[1]; // Артикул
            $color_number = $matches[2]; // Номер цвета
            $color_info = substr($line, strlen($sku) + 1); // Полная информация о цвете
            
            // Находим вариацию с указанным артикулом
            $variation_id = find_variation_by_sku($sku);
            
            if (!$variation_id) {
                $log[] = "Вариация с артикулом {$sku} не найдена";
                continue;
            }
            
            // Обновляем атрибут color у вариации
            $color_update_result = update_variation_color($variation_id, $color_info);
            
            if (!$color_update_result) {
                $log[] = "Не удалось обновить цвет для вариации {$sku}";
            }
            
            // Извлекаем название модели из названия продукта для поиска фото
            $model_name = extract_model_name($current_product);
            
            // Ищем подходящее изображение
            $image_path = find_image_by_model_and_color($images_directory, $model_name, $color_number);
            
            if ($image_path) {
                // Присваиваем изображение вариации
                $image_result = assign_image_to_variation($variation_id, $image_path);
                
                if ($image_result) {
                    $log[] = "Успешно: Вариация {$sku}, цвет: {$color_info}, изображение: " . basename($image_path);
                    $processed_count++;
                } else {
                    $log[] = "Ошибка: Не удалось присвоить изображение для вариации {$sku}";
                }
            } else {
                $log[] = "Изображение для модели '{$model_name}' и цвета '{$color_number}' не найдено";
            }
        } else {
            // Это название продукта
            $current_product = $line;
        }
    }
    
    $log[] = "Обработка завершена. Успешно обработано: {$processed_count} вариаций.";
    
    return implode("\n", $log);
}

/**
 * Извлекает название модели из полного названия продукта
 */
function extract_model_name($product_name) {
    // Из примера "Drops Soft Tweed" получаем "Soft Tweed"
    // Предполагаем, что первое слово - это бренд
    $parts = explode(' ', $product_name, 2);
    return isset($parts[1]) ? $parts[1] : $product_name;
}

/**
 * Находит вариацию товара по артикулу
 */
function find_variation_by_sku($sku) {
    global $wpdb;
    
    // Ищем ID продукта с указанным артикулом
    $variation_id = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
         WHERE meta_key = '_sku' AND meta_value = %s",
        $sku
    ));
    
    return $variation_id;
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
    $parent->save();
    
    return true;
}

/**
 * Ищет изображение по модели и номеру цвета
 */
function find_image_by_model_and_color($images_directory, $model_name, $color_number) {
    // Проверяем, существует ли корневая директория
    if (!is_dir($images_directory)) {
        return false;
    }
    
    // Ищем папку, содержащую название модели
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
        return false;
    }
    
    // Ищем фото с номером цвета (например, 01.jpg, 01-2.jpg)
    $images = glob($model_dir . $color_number . '{,.*,-*.*}', GLOB_BRACE);
    
    if (empty($images)) {
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
    
    // Копируем файл во временную директорию
    $temp_file = wp_tempnam(basename($image_path));
    copy($image_path, $temp_file);
    
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
        return false;
    }
    
    // Устанавливаем изображение как основное для вариации
    update_post_meta($variation_id, '_thumbnail_id', $attachment_id);
    
    return true;
}

// Запускаем обработку и выводим результаты
echo update_product_variations_with_colors();
?>