# Решение проблем (Troubleshooting)

Этот документ поможет вам разобраться с наиболее распространенными проблемами, которые могут возникнуть при работе скрипта, и предложит способы их решения.

## Диагностика проблем

Перед решением проблемы важно правильно её диагностировать. Скрипт создает подробный журнал в файле `color-matcher-log.txt` в корневой директории WordPress. Проверьте его содержимое для выяснения причины проблемы.

## Распространенные проблемы и их решения

### 1. Не найден файл соответствий

**Проблема:** В логах сообщение "Файл соответствий не найден".

**Решение:**
1. Проверьте, загружен ли файл `соответствия.txt` в указанную директорию
2. Проверьте правильность пути в настройках скрипта:
   ```php
   $correspondence_file = ABSPATH . 'wp-content/uploads/correspondence/соответствия.txt';
   ```
3. Проверьте права доступа к файлу (должны быть не менее 644)
4. Убедитесь, что файл имеет правильную кодировку (предпочтительно UTF-8)

### 2. Не найдены изображения для модели и цвета

**Проблема:** В логах сообщение "Изображение для модели X и цвета Y не найдено".

**Решение:**
1. Проверьте, существует ли папка с названием модели:
   ```
   wp-content/uploads/product_images/Soft Tweed/
   ```
2. Проверьте, есть ли в папке файлы с номером цвета (например, "01.jpg")
3. Сравните названия папок с тем, что извлекает функция `extract_model_name()`
4. Модифицируйте функцию извлечения названия модели, если нужно:
   ```php
   function extract_model_name($product_name) {
       // Добавьте дополнительные проверки для вашего формата названий
       if (preg_match('/Drops\s+([^,\s]+)/', $product_name, $matches)) {
           return $matches[1];
       }
       
       // Пример: для формата "X by Drops"
       if (preg_match('/([^,\s]+)\s+by\s+Drops/', $product_name, $matches)) {
           return $matches[1];
       }
       
       // Остальная логика...
   }
   ```

### 3. Не удается извлечь номер цвета

**Проблема:** В логах сообщение "Не удалось извлечь номер цвета из X для вариации Y".

**Решение:**
1. Изучите формат строк с цветами в файле соответствий
2. Модифицируйте функцию `extract_color_number()` для поддержки вашего формата:
   ```php
   function extract_color_number($color_info) {
       // Стандартный формат "XX / название"
       if (preg_match('/^(\d+)\s*\//', $color_info, $matches)) {
           return $matches[1];
       }
       
       // Формат "color-XX" или "colorXX"
       if (preg_match('/color-?(\d+)/i', $color_info, $matches)) {
           return $matches[1];
       }
       
       // Другие форматы...
   }
   ```

### 4. Не удается создать термин цвета

**Проблема:** В логах сообщение "Не удалось обновить цвет для вариации X".

**Решение:**
1. Проверьте, есть ли у WordPress права на запись в базу данных
2. Убедитесь, что атрибут "color" (pa_color) существует и настроен как глобальный в WooCommerce
3. Проверьте, не превышает ли название цвета максимальную длину для терминов (обычно 200 символов)
4. Попробуйте создать термин вручную через админ-панель для проверки

### 5. Скрипт работает очень медленно или зависает

**Проблема:** Выполнение скрипта занимает слишком много времени или зависает.

**Решение:**
1. Ограничьте количество обрабатываемых товаров за один раз:
   ```php
   function update_product_variations_with_colors($limit = 50, $offset = 0) {
       // ... 
       $products = wc_get_products([
           'type' => 'variable',
           'limit' => $limit,
           'offset' => $offset,
       ]);
       // ...
   }
   ```
2. Реализуйте пагинацию и обработку порциями:
   ```php
   function run_color_matcher_in_batches() {
       $batch_size = 20;
       $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
       
       $results = update_product_variations_with_colors($batch_size, $offset);
       
       $next_offset = $offset + $batch_size;
       echo "<p>Обработано {$batch_size} товаров, начиная с позиции {$offset}.</p>";
       echo "<a href='?action=run_matcher&offset={$next_offset}'>Обработать следующую порцию</a>";
       
       echo "<pre>{$results}</pre>";
   }
   ```
3. Оптимизируйте функцию поиска изображений, кэшируя результаты поиска директорий

### 6. Не удается загрузить изображение в медиабиблиотеку

**Проблема:** В логах сообщение "Ошибка при загрузке изображения".

**Решение:**
1. Проверьте права на запись в папку `wp-content/uploads/`
2. Убедитесь, что файл изображения не поврежден
3. Проверьте настройки PHP:
   - Достаточно ли выделено памяти (`memory_limit`)
   - Настройка `upload_max_filesize` позволяет загружать файл такого размера
   - Подходящий лимит времени выполнения (`max_execution_time`)
4. Попробуйте загрузить изображение вручную для проверки

### 7. Проблемы с кодировкой файла соответствий

**Проблема:** Скрипт не может корректно прочитать названия цветов из файла (отображаются некорректные символы).

**Решение:**
1. Убедитесь, что файл соответствий сохранен в кодировке UTF-8
2. Используйте функции для корректного чтения файла:
   ```php
   $file_content = file_get_contents($correspondence_file);
   if (mb_detect_encoding($file_content, 'UTF-8', true) === false) {
       $file_content = mb_convert_encoding($file_content, 'UTF-8', 'Windows-1251');
   }
   ```
3. Если проблема не решается, сохраните файл в другой кодировке и измените скрипт соответственно

### 8. Нет прав на выполнение операций

**Проблема:** Скрипт сообщает о недостаточных правах для выполнения определенных операций.

**Решение:**
1. Проверьте, что пользователь WordPress, выполняющий скрипт, имеет достаточные права
2. Проверьте права доступа к файлам:
   ```
   chmod 644 соответствия.txt
   chmod -R 755 wp-content/uploads/product_images/
   ```
3. Если скрипт запускается через WPCode, убедитесь, что плагин настроен на выполнение с правами администратора

### 9. Изображения привязываются к неправильным вариациям

**Проблема:** Скрипт привязывает изображения, но к неверным вариациям товаров.

**Решение:**
1. Проверьте логику соотнесения артикулов с цветами в скрипте
2. Убедитесь, что в файле соответствий правильно указаны артикулы и цвета
3. Проверьте, что у вариаций в WooCommerce правильно заполнено поле артикула (SKU)

### 10. Конфликты с другими плагинами

**Проблема:** Скрипт конфликтует с другими плагинами WooCommerce.

**Решение:**
1. Временно отключите другие плагины, которые могут влиять на работу с атрибутами или изображениями
2. Запустите скрипт в безопасном режиме, отключив хуки других плагинов:
   ```php
   function run_color_matcher_safely() {
       // Отключаем хуки, которые могут мешать
       remove_all_actions('woocommerce_before_product_object_save');
       remove_all_actions('woocommerce_after_product_object_save');
       
       // Запускаем скрипт
       $results = update_product_variations_with_colors();
       
       echo "<pre>{$results}</pre>";
   }
   ```

## Расширенная диагностика

Если простые решения не помогают, можно добавить дополнительные инструменты диагностики:

### Подробное логирование

Добавьте более подробное логирование для выявления проблемы:

```php
function write_debug_log($message, $data = null, $logfile = 'color-matcher-debug.txt') {
    $time = date('[Y-m-d H:i:s]');
    $message = $time . ' ' . $message;
    
    if ($data !== null) {
        $message .= "\nData: " . print_r($data, true);
    }
    
    $message .= "\n";
    
    $logpath = ABSPATH . $logfile;
    file_put_contents($logpath, $message, FILE_APPEND);
}
```

### Проверка состояния WooCommerce

Добавьте функцию для проверки состояния WooCommerce:

```php
function check_woocommerce_status() {
    $diagnostics = [];
    
    // Проверяем активность WooCommerce
    $diagnostics['woocommerce_active'] = class_exists('WooCommerce');
    
    // Проверяем наличие атрибута color
    $color_tax = get_taxonomy('pa_color');
    $diagnostics['color_attribute_exists'] = !empty($color_tax);
    
    // Проверяем количество товаров
    $product_count = wp_count_posts('product');
    $diagnostics['product_count'] = $product_count->publish;
    
    // Проверяем количество вариаций
    $variation_count = wp_count_posts('product_variation');
    $diagnostics['variation_count'] = $variation_count->publish;
    
    return $diagnostics;
}
```

### Тестовый режим

Реализуйте тестовый режим для проверки функций без внесения изменений:

```php
function test_color_matcher($sku) {
    global $correspondence_file, $images_directory;
    
    // Считываем файл соответствий
    $file_content = file_get_contents($correspondence_file);
    $lines = explode("\n", $file_content);
    
    $color_info = null;
    
    // Ищем артикул в файле
    foreach ($lines as $line) {
        if (strpos($line, $sku) === 0) {
            $color_info = substr($line, strlen($sku) + 1);
            break;
        }
    }
    
    if (!$color_info) {
        return "Артикул {$sku} не найден в файле соответствий.";
    }
    
    // Ищем вариацию
    $variation_id = find_variation_by_sku($sku);
    
    if (!$variation_id) {
        return "Вариация с артикулом {$sku} не найдена в WooCommerce.";
    }
    
    $variation = wc_get_product($variation_id);
    $product_id = $variation->get_parent_id();
    $product = wc_get_product($product_id);
    $product_name = $product->get_name();
    
    // Извлекаем название модели
    $model_name = extract_model_name($product_name);
    
    // Извлекаем номер цвета
    $color_number = extract_color_number($color_info);
    
    // Ищем изображение
    $image_path = find_image_by_model_and_color($images_directory, $model_name, $color_number);
    
    $result = [
        'sku' => $sku,
        'color_info' => $color_info,
        'product_name' => $product_name,
        'model_name' => $model_name,
        'color_number' => $color_number,
        'image_path' => $image_path,
        'image_exists' => !empty($image_path) && file_exists($image_path),
    ];
    
    return $result;
}
```

## Контакты для поддержки

Если после применения всех рекомендаций проблема не решена, обратитесь за помощью через GitHub, создав Issue в репозитории.

Укажите:
1. Описание проблемы
2. Лог с ошибкой
3. Версии WordPress, WooCommerce и PHP
4. Примеры данных, на которых возникает проблема
