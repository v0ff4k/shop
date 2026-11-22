# Legacy-шаблоны пользователя

Файлы перенесены сюда из `src/Store/UserBundle/Resources/views` в рамках пункта 45 TODO.

Это overrides эпохи FOSUserBundle, сохранённые как архив для справки. Активные
страницы пользователя находятся в `templates/user/*` и рендерятся из
`src/UI/Controller/User/*`.

FOSUserBundle не зарегистрирован в `config/bundles.php`; не подключайте эти
шаблоны к роутингу без отдельной миграции.
