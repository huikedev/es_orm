## huikedev/es_orm

> elasticsearch orm

### 配置

和ThinkPhp的数据库配置基本一致。

```php 
<?php
return [
    'default'=>'default',
    'connections'=>[
        'default'=>[
            'hosts'=>[
                [
                    // 服务器地址
                    'host'        => '192.168.9.101',
                    // 端口
                    'port'        => '9200',
                    // 协议
                    'scheme' => 'http',
                    // 路径
                    'path' => '',
                    // 用户名
                    'username' => '',
                    // 密码
                    'password' => ''
                ]
            ],

        ]
    ]
];
```

### 定义Model

```php
<?php

namespace app\es_model;

use huikedev\es_orm\model\EsModel;

class User extends EsModel
{
    protected string $index = 'user';
    protected array $esMappings=[
        'dynamic'=>true,
        'properties'=>[
            'id'=>['type'=>'integer'],
            'full_name'=>['type'=>'keyword'],
            'gender'=>['type'=>'integer'],
            'reg_time'=>['type'=>'integer']
        ]
    ];
}
```

### 创建索引

```php
\app\es_model\User::createIndex();
```

### 更新索引

修改`User`中的`$esMappings`，然后执行：

```php
\app\es_model\User::updateMappings();
```

### 新增/修改数据

`id`存在则为修改，不存在则为新增。

```php
$user = new \app\es_model\User();
$user->id = 1;
$user->full_name = 'huikedev';
$user->gender = 1;
$user->reg_time = 1622527143;
$user->save();
```

### 查询数据

```php
$user = \app\es_model\User::whereTerm('full_name','huikedev')->findOrEmpty();
$userList = \app\es_model\User::whereTerm('full_name','huikedev')->select();
$userPage = \app\es_model\User::whereTerm('full_name','huikedev')->paginate();
```

### 布尔查询

```php
// 查询 full_name === 'huikedev' AND gender !==1 的数据
$user = \app\es_model\User::queryMust(function (\huikedev\es_orm\contract\QueryInterface $query){
    $query->whereTerm('full_name','huikedev');
})->queryMustNot(function (\huikedev\es_orm\contract\QueryInterface $query){
    $query->whereTerm('gender',1);
})->findOrEmpty();
```