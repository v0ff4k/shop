Все пункты должны быть отмечены перед отправкой.

# General
  - [ ] Код работает
  - [ ] Код легко читаем
  - [ ] Follows coding conventions
  - [ ] Имена полны и коротки до минимума
  - [ ] Имена граматически верны
  - [ ] Имена содержат ед. измерения.
  - [ ] Нет магических номеров ($а + 1.2)
  - [ ] Никакого "хардкода"
  - [ ] Все переменные  camel/camelCase
  - [ ] Отсутствие закомментированного кода
  - [ ] Нет "мертвого кода" (inaccessible at Runtime)
  - [ ] Нет кода который можно заменить имеющимися библиотеками
  - [ ] Переменные не используют null значения
  - [ ] Переменные контролируются кодом и логически понятны, нет неожидоннастей.
  - [ ] Код не повторяется и не дублируется
  - [ ] Всегда идут парами if + else 
  - [ ] Нет сложных/длинных boolean выражений
  - [ ] Нет зарезервированных имен
  - [ ] Отсутствуют пустые блоки кода
  - [ ] Данные структурированы
  - [ ] Конструкторы не принимают null/none значения
  - [ ] Catch обязательно перехватывают ожидаемые исключения
  - [ ] Exceptions are not eaten if caught, unless explicitly documented otherwise
  - [ ] Files/Sockets and other resources are properly closed even when an exception occurs in using them
  - [ ] `null` ни в одном методе не возвращается
  - [ ] Операторы == и === (включая !==) не спутаны
  - [ ] Числа с плавающей точкой не сравниваются
  - [ ] Петли for/foreach/while не бесконечны и имеют ясный финиш
  - [ ] Код в петлях краток и понятен
  - [ ] No methods with boolean parameters
  - [ ] Объект(ы) существует пока он нужен
  - [ ] Нет утечек памяти(unset() e.t.c.)
  - [ ] Code is unit testable
  - [ ] Test cases are written wherever possible
  - [ ] Methods return early without compromising code readability
  - [ ] Performance is considered
  - [ ] Loop iteration and off by one are taken care of

# Architecture
  - [ ] Design patterns if used are correctly applied
  - [ ] [Law of Demeter](http://c2.com/cgi/wiki/LawOfDemeter?LawOfDemeter) is not violated
  - [ ] A class should have only a single responsibility (i.e. only one potential change in the software's specification should be able to affect the specification of the class)
  - [ ] Classes, modules, functions, etc. should be open for extension, but closed for modification
  - [ ] Objects in a program should be replaceable with instances of their subtypes without altering the correctness of that program
  - [ ] Many client-specific interfaces are better than one general-purpose interface.
  - [ ] Depend upon Abstractions. Do not depend upon concretions.

# API
  - [ ] APIs and other public contracts check input values and fail fast
  - [ ] API checks for correct oauth scope / user permissions
  - [ ] Any API change should be reflected in the API documentation
  - [ ] APIs return correct status codes in responses

# Logging
  - [ ] Logging should be easily discoverable
  - [ ] Required logs are present
  - [ ] Frivolous logs are absent
  - [ ] Debugging code is absent
  - [ ] No `print_r`, `var_dump` or similar calls exist
  - [ ] No stack traces are printed
  
# Documentation
  - [ ] Comments should indicate WHY rather that WHAT the code is doing
  - [ ] All methods are commented in clear language.
  - [ ] Comments exist and describe rationale or reasons for decisions in code
  - [ ] All public methods/interfaces/contracts are commented describing usage
  - [ ] All edge cases are described in comments
  - [ ] All unusual behaviour or edge case handling is commented
  - [ ] Data structures and units of measurement are explained


# Security
  - [ ] All data inputs are checked (for the correct type, length/size, format, and range)
  - [ ] Invalid parameter values handled such that exceptions are not thrown
  - [ ] No sensitive information is logged or visible in a stacktrace