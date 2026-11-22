#!/bin/bash

# Для src/UI/Resolver/RequestDtoResolver.php

# Путь к папке с исходным кодом (измените 'src', если у вас другая структура)
SRC_DIR="src"

if [ ! -d "$SRC_DIR" ]; then
    echo "Ошибка: Директория $SRC_DIR не найдена. Запустите скрипт из корня проекта."
    exit 1
fi

echo "Список найденных DTO классов:"
echo "---------------------------"

# 1. Ищем все файлы *Dto.php
# 2. Убираем расширение .php
# 3. Заменяем '/' на '\'
# 4. Превращаем первую букву 'src' в 'App' (согласно стандарту PSR-4 в Symfony)
find "$SRC_DIR" -type f -name "*Dto.php"  | \
# remove ending .php
sed -E 's/\.php$/;/'  | \

# remove "string\" -> ""
# sed -E 's/.+\///g'

# re-filter slashes "/" -> "\"
 sed 's/\//\\/g' | \


# replace   starting with src with App !
 sed -E 's/^src/use App/'

echo "---------------------------"
