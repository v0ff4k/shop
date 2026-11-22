#!/bin/bash
# Исправление дублированных thumb/thumb путей

for base_dir in /var/www/shop/public/uploads/products /var/www/shop/public/uploads/categories /var/www/shop/public/uploads/users; do
    if [ -d "$base_dir/thumb/thumb" ]; then
        echo "Исправление $base_dir/thumb/thumb -> $base_dir/thumb"
        mkdir -p "$base_dir/thumb"
        mv "$base_dir/thumb/thumb"/* "$base_dir/thumb/" 2>/dev/null
        rmdir "$base_dir/thumb/thumb" 2>/dev/null
    fi
done

echo "Готово!"
find /var/www/shop/public/uploads -path "*/thumb/*" -type f | head -5
