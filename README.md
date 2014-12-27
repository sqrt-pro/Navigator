# SQRT\Navigator

Компонент позволяет автоматизировать или упростить работу с пагинацией, сортировкой и фильтрацией списка элементов.

Работа начинается с инициализации объекта Navigator с передачей в него объектов Request и Manager.

`$navi = new Navigator($request, $manager);`

## Пагинация

Объект Navigator предоставляет набор методов, для получения первой, последней, следующей, предыдущей страниц, а также 
быстрой генерации объектов URL для них. 

~~~ php
$navi->getPage(); // получение номера текущей страницы
$navi->getFirstPage(); // номер первой страницы
$navi->getLastPage(); // номер последней страницы
$navi->getNextPage(); // следующая страница от текущей
$navi->getPrevPage(); // предыдущая страница от текущей
~~~
    
По аналогии, для всех методов есть возможность получить готовый объект URL:

~~~ php
$navi->getPageUrl(42); // URL с номером страницы 42
$navi->getFirstPageUrl(); // URL с первой страницей
~~~
    
Функционал постраничности требует, чтобы у Navigator было известно общее количество элементов в выборке и указано 
количество элементов на странице.

~~~ php
$navi->setOnpage(10); // Указываем количество элементов на странице
$navi->setTotal(42); // Указываем количество элементов в выборке
echo $navi->getTotalPages(); // Результат - количество страниц: 5
~~~
    
## Сортировка

Navigator позволяет задать допустимые варианты сортировки, а также сформировать ссылки на прямую и обратную сортировку. 

~~~ php
$navi = new Navigator($request, new URL('hello'));
$navi->addOrderBy('id'); // Именование и в БД, и в URL совпадает
$navi->addOrderBy('total', 'Количество', 'count(*)'); // В URL будет параметр вида orderby:total, в SQL `count(*)`
~~~
    
После добавления можно получить полный список вариантов сортировки, или отдельные пункты, для формирования списков опций 
или ссылок в интерфейсе. Каждый вариант сортировки - это объект OrderBy:

~~~ php
$o = $navi->getOrderByOption('total'); // Получили объект
$o->asUrlParameter(); // Значение для подстановки в URL: total - сортировка по-возрастанию
$o->asUrlParameter(false); // _total - сортировка по убыванию
$o->asSQL(); // SQL выражение для сортировки, в данном случае count(*) ASC
$o->asUrl(); // Объект URL
~~~
    
Если в адресе содержится параметр сортировки, например `.../orderby:_total/`, можно получить текущий порядок для сортировки:

~~~ php
$navi->getOrderBy();
~~~
    
Если адрес не содержит выбора для сортировки, метод вернет `false` или способ сортировки по-умолчанию, если он был указан 
с помощью метода `$navi->setDefaultOrderBy()`.

## Выборка элементов, подсчет количества записей

Navigator может сразу обрабатывать данные о сортировке, фильтрах и пагинации, применять их и получать кол-во элементов 
в выборке, автоматически расчитывать количество страниц и выдавать соответствующие этим настройкам объекты. 

Если не требуется сложная логика фильтрации, можно воспользоваться настройками, или отнаследоваться от класса и 
переопределить соответствующие методы.

~~~ php
$navi->setCollection('Users'); // Указываем, что используем коллекцию Users
$navi->getTotal(); // Получение общего количества элементов в выборке
$navi->setOnpage(10); // Указываем количество элементов на странице
$navi->getItems(); // Получаем нужный набор элементов, с учетом сортировки и текущей страницы
$navi->each($callable); // Применение $callable к текущей выборке элементов
~~~

## Фильтрация

Механизм фильтрации работает с помощью назначения фильтров - объектов Filter. Базово предусмотрено три фильтра - Equal, 
Between и Like, выполняющие фильтрацию по прямому соответствию, выборке диапазона и условию LIKE. При необходимости 
реализации более сложной логики можно добавлять произвольные фильтры с использованием анонимных функций или создавать 
свои фильтры наследуясь от класса Filter.

Все фильтры добавляют новые условия (Объект `SQRT\DB\Conditions`) для последующей выборки. Можно задать условия по-умолчанию:

~~~ php
$navi
  ->conditions(true) // Флаг true указывает что это условия по-умолчанию
  ->equal('type', 'new')
  ->greaterOrEqual('age', 18);
~~~

Или добавить динамические фильтры:

~~~ php
$navi->addFilterEqual('one', 'is_numeric'); // Валидация может выполняться с помощью callable-выражений
$navi->addFilterEqual('two', '/^[a-z]+$/'); // Валидация может выполняться регулярными выражениями
$navi->addFilterBetween('date'); // На свой страх и риск можно обойтись и без валидации
$navi->addFilter(
  'some',
   null,
  function(Navigator $navi, Filter $filter) {
    if ($val = $filter->getCleanValue()) {
        $navi->conditions()->expr('... some complicated expression ...');
    }
  }
); // Произвольный фильтр
~~~
    
После формирования набора правил, фильтр можно использовать для автоматической обработки запросов и получения 
"чистых данных" из URL:

~~~ php
// .../one:42/
echo $navi->getFilter('one')->getCleanValue(); // Результат: 42

// .../one:two/
echo $navi->getFilter('one')->getCleanValue(); // Результат: false
~~~
    
Либо можно получать уже сформированный набор Conditions для SQL запросов с помощью метода `processFilters()`.

Callback, указываемый в фильтре, позволяет автоматизировать создание условий для выборки. В функцию передаются как 
аргументы объекты Navigator и Filter. После проверки и обработки данных, Callback-функция должна подготовить условие 
выборки и передать его в Navigator с помощью метода `conditions()`.

Таким образом, в результате работы всех фильтров, получается готовый набор условий, который можно использовать вручную 
или автоматически в методах `getItems()` и `countTotal()`.

Изначально предусмотренные фильтры Equal, Between и Like являются наиболее часто используемыми вариантами:
* **Equal:** прямое соответствие field = value
* **Between:** значение должно быть в диапазоне field >= column_from и field <= column_to
* **Like:** генерирует условие [column] LIKE "[value]%"

Для вышеописанного набора правил, URL и результат будет следующим:

~~~ php
URL: /hello/one:1/two:three/date_from:01.01.2014/date_to:31.12.2014/
Результат: `one`=1 AND `two`="three AND `date` >= "01.01.2014" AND `date` <= "31.12.2014"
~~~

Чистый URL
----------

Важным плюсом работы Navigator`а является то, что вся навигация осуществляется по "чистым адресам". Т.е. если навигатор 
настроен на параметры one и two, а в адресе будут указаны мусорные параметры, то в ссылках пагинатора и сортировки 
лишних параметров не будет.

~~~ php
$navi = new Navigator($request, new URL('hello', array('one' => 1, 'two' => 2, 'test'=>'me'));
$navi->addFilterEqual('one');
$navi->addFilterEqual('two');
$navi->setPage(42);
echo $navi->getUrlClean()->toString(); // /hello/one:1/two:2/page:42/
~~~

Генерация элементов интерфейса
------------------------------

Для сортировки и фильтрации, кроме возможности просто получить объект URL, также предусмотрены методы для формирования 
HTML элементов интерфейса. Фильтры будут рендериться с текущими значениями фильтров и сортировки, с учетом валидности 
этих данных.

Для генерации SELECT:

~~~ php
$navi->getOrderByAsSelect(); // Генерация тега <select> с полным списком опций
~~~
    
Для фильтров предусмотрена возможность сгенерировать поле ввода INPUT или SELECT, если у него указаны опции выбора 
`$f->setOptions(array(...))`:

~~~ php
$f = $navi->getFilter('one'); // Получаем объект Filter
$f->asInput(); // Генерация тега <input>
$f->asSelect(); // Генерация тега <select> с полным списком опций
~~~ 
