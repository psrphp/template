# PHP模板引擎

一个功能强大的模板引擎，非常灵活

## 安装

``` cmd
composer require psrphp/template
```

## 用例

``` php
$template = new \PsrPHP\Template\Template('可以传入一个 Psr\SimpleCache\CacheInterface 实例，传入后会开启模板缓存，提高效率');

// 添加分区
$template->addPath('book','/www/template/book');
$template->addPath('cms','/www/template/cms', 1);
$template->addPath('cms','/www/template/cms2', 2);

// 声明变量
$template->assign('name','小刘');
$template->assign('sex','男');
$template->assign([
    'name'=>'小刘',
    'sex'=>'男'
]);

// 渲染
$template->renderFromFile('content@cms'); // 渲染cms分区下的content模板 系统会根据权重顺序在添加的分区目录中依次寻找content模板
$template->renderFromFile('index@book'); // 渲染book分区下的index模板

// 直接渲染模板字符串
$template->renderFromString('hello {$name}', ['name'=>'隔壁小王']); // 直接渲染字符串模板

// 语法扩展
// 定一个{datetime}，渲染成当前时间
$template->extend('/\{datetime\}/Ui', function ($matchs) {
    return '<?php echo date(\'Y-m-d H:i:s\');?>';
});
$template->renderFromString('当前时间是：{datetime}');
```

## 支持的模板语法

### 变量输出

所有的变量输出都进行了htmlspecialchars编码

若要原始输出，可使用{echo $name} {echo $vo['title']} {php echo $vo['title']}

``` php
{$name}
{$vo.title}
{$vo['title']}

// 原始输出
{echo $name}
{echo $vo['title']}
{php echo $vo['title']}
```

### 使用函数

``` php
{:mb_substr($title, 0, 80)}
{:date('Y-m-d', $time)}

// 原始输出
{echo date('Y-m-d', time())}
{php echo date('Y-m-d', time())}
{php print_r($list)}
```

### function 定义函数

``` html

// 定义函数
{function somefunc($a, $b)}
<code>{$a}</code>与<code>{$b}</code>{$b}求和，其值是<code>{echo $a+$b}<code>
{/function}

// 执行函数
{echo somefunc(1,2)}
```

### php 原始代码

``` php

// 方式一
<?php 
echo $title;
?>

// 方式二
{php echo $title}

// 方式三
{php}
echo $title;
{/php}
```

### include 引入模板

``` php
{include common/header@cms}
{include common/footer@cms}
```

### foreach 循环

``` php
{foreach $list as $key=>$vo}
{$key}:{$vo}
{/foreach}
```

### for 循环

``` php
{for $i=0; $i < 10; $i++}
{$i}
{/for}
```

### if else 条件判断

``` php
{if isset($a)}
// somecode
{elseif $b==1}
// somecode
{else}
// somecode
{/if}
```

### switch 条件

``` php
{switch $a}

{case 1}
// some..
{/case}

{case 2}
// some..
{/case}

{default}
// some..
{/default}

{/switch}
```
