# Примеры использования

В этом документе приведены примеры того, как скрипт работает с реальными данными. Эти примеры помогут вам лучше понять логику скрипта и адаптировать его под ваши нужды.

## Пример 1: Соотнесение вариации с цветом

Давайте рассмотрим пример с артикулом `nOyBG8vJiyYxgwnsoqoAb3` из файла соответствий.

### Исходные данные:
В файле соответствий строка выглядит так:
```
nOyBG8vJiyYxgwnsoqoAb3 01 / off white uni
```

### Что делает скрипт:
1. Находит вариацию товара с артикулом `nOyBG8vJiyYxgwnsoqoAb3`
2. Извлекает информацию о цвете: `01 / off white uni`
3. Извлекает номер цвета: `01`
4. Определяет название модели из названия товара (например, "Soft Tweed")
5. Ищет изображения в папке модели с номером цвета `01` (например, `01.jpg`, `01-2.jpg` и т.д.)
6. Устанавливает атрибут "color" для вариации значением `01 / off white uni`
7. Присваивает найденное изображение к вариации

### Код:
```php
// Находим вариацию с указанным артикулом
$sku = 'nOyBG8vJiyYxgwnsoqoAb3';
$color_info = '01 / off white uni';
$variation_id = find_variation_by_sku($sku);

// Обновляем атрибут color у вариации
update_variation_color($variation_id, $color_info);

// Извлекаем номер цвета
$color_number = extract_color_number($color_info); // Результат: "01"

// Извлекаем название модели из названия продукта
$product_name = 'Drops Soft Tweed';
$model_name = extract_model_name($product_name); // Результат: "Soft Tweed"

// Ищем подходящее изображение
$image_path = find_image_by_model_and_color($images_directory, $model_name, $color_number);

// Присваиваем изображение вариации
assign_image_to_variation($variation_id, $image_path);
```

## Пример 2: Обработка разных форматов данных

Скрипт может обрабатывать разные форматы соответствий в файле:

### Примеры строк из файла соответствий:
```
nOyBG8vJiyYxgwnsoqoAb3 01 / off white uni
B1MB7xcjgNIq5ARwBCI442 green
7f70ec36-75ac-11ef-0a80-1913002bd046 1-12
```

### Как скрипт обрабатывает эти строки:

1. Для строки `nOyBG8vJiyYxgwnsoqoAb3 01 / off white uni`:
   - Артикул: `nOyBG8vJiyYxgwnsoqoAb3`
   - Цвет: `01 / off white uni`
   - Номер цвета: `01`

2. Для строки `B1MB7xcjgNIq5ARwBCI442 green`:
   - Артикул: `B1MB7xcjgNIq5ARwBCI442`
   - Цвет: `green`
   - Номер цвета: не удается извлечь, использует само название цвета

3. Для строки `7f70ec36-75ac-11ef-0a80-1913002bd046 1-12`:
   - Артикул: `7f70ec36-75ac-11ef-0a80-1913002bd046`
   - Цвет: `1-12`
   - Номер цвета: `1`

### Код для извлечения номера цвета:
```php
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
```

## Пример 3: Поиск папок и файлов изображений

### Структура папок и файлов:
```
product_images/
  ├── Soft Tweed/
  │   ├── 01.jpg     # Основное изображение для цвета 01
  │   ├── 01-2.jpg   # Дополнительное изображение для цвета 01
  │   ├── 02.jpg     # Изображение для цвета 02
  │   └── ...
  ├── Sky/
  │   ├── 01.jpg
  │   ├── 02.jpg
  │   └── ...
  └── ...
```

### Как скрипт ищет изображения:

1. Для продукта "Drops Soft Tweed" и цвета "01":
   - Скрипт определяет название модели: "Soft Tweed"
   - Ищет папку, содержащую "Soft Tweed" в названии
   - В этой папке ищет файлы, начинающиеся с "01"
   - Если найдено несколько файлов (например, 01.jpg и 01-2.jpg), выбирает наименьший по размеру

### Код:
```php
function find_image_by_model_and_color($images_directory, $model_name, $color_number) {
    // Ищем папку, содержащую название модели
    $model_dir = null;
    $dirs = glob($images_directory . '*', GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        $dir_name = basename($dir);
        
        if (stripos($dir_name, $model_name) !== false) {
            $model_dir = $dir . '/';
            break;
        }
    }
    
    if (!$model_dir) {
        return false;
    }
    
    // Ищем файлы с номером цвета
    $pattern = $model_dir . $color_number . '*.*';
    $images = glob($pattern);
    
    if (empty($images)) {
        return false;
    }
    
    // Если найдено несколько изображений, выбираем наименьшее по размеру
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
    
    return $images[0];
}
```

## Пример 4: Обновление атрибутов товара

### Как скрипт обновляет атрибуты:

1. Для цвета "01 / off white uni":
   - Проверяет, существует ли таксономия "pa_color"
   - Проверяет, существует ли термин "01 / off white uni" в таксономии
   - Если термин не существует, создает его
   - Обновляет атрибут вариации значением термина
   - Добавляет термин к родительскому товару

### Код:
```php
function update_variation_color($variation_id, $color_value) {
    // Получаем объект вариации
    $variation = wc_get_product($variation_id);
    
    // Получаем родительский товар
    $parent_id = $variation->get_parent_id();
    $parent = wc_get_product($parent_id);
    
    // Определяем таксономию для цвета
    $color_taxonomy = 'pa_color';
    
    // Проверяем, существует ли таксономия
    if (!taxonomy_exists($color_taxonomy)) {
        register_taxonomy($color_taxonomy, 'product', [
            'hierarchical' => false,
            'label' => 'Color',
            'query_var' => true,
            'rewrite' => array('slug' => 'color')
        ]);
    }
    
    // Проверяем, существует ли термин с таким значением
    $term = get_term_by('name', $color_value, $color_taxonomy);
    
    if (!$term) {
        // Создаем новый термин
        $term = wp_insert_term($color_value, $color_taxonomy);
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
    
    return true;
}
```

## Пример 5: Работа с различными названиями моделей

### Различные форматы названий товаров:
```
Drops Soft Tweed
Drops Sky
Sky (Drops)
```

### Как скрипт извлекает название модели:
```php
function extract_model_name($product_name) {
    // Ищем "Drops X", где X - название модели
    if (preg_match('/Drops\s+([^,\s]+)/', $product_name, $matches)) {
        return $matches[1];
    }
    
    // Ищем "X (Drops)", где X - название модели
    if (preg_match('/([^,\s]+)\s+\(Drops\)/', $product_name, $matches)) {
        return $matches[1];
    }
    
    // Если не нашли, предполагаем, что первое слово - это бренд
    $parts = explode(' ', $product_name, 2);
    return isset($parts[1]) ? $parts[1] : $product_name;
}
```

## Советы по адаптации скрипта

1. **Настройка функции извлечения названия модели**
   - Если ваши названия товаров имеют другой формат, отредактируйте функцию `extract_model_name()`
   - Добавьте дополнительные шаблоны регулярных выражений для покрытия всех возможных вариантов

2. **Настройка функции извлечения номера цвета**
   - Если ваши строки с цветами имеют нестандартный формат, отредактируйте функцию `extract_color_number()`
   - Добавьте дополнительную логику для случаев, когда номер цвета задается не числом

3. **Обработка особых случаев**
   - Для товаров с нестандартными названиями или структурой, добавьте специальные правила обработки
   - Используйте массивы соответствий для особых случаев, которые не вписываются в общую логику

4. **Оптимизация производительности**
   - Для больших магазинов обрабатывайте товары порциями
   - Используйте кэширование результатов поиска папок и файлов
   - Реализуйте возможность возобновления обработки с места остановки

Эти примеры демонстрируют основные принципы работы скрипта и помогут вам адаптировать его под ваши конкретные потребности.
