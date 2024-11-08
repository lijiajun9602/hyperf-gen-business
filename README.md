# PHP Gen Business Api 
基于 [Hyperf](https://github.com/hyperf/hyperf) 框架的 根据表生成相应的接口

## 优点

- 可生成相应的接口代码
- 可生成相应的接口文档
- 可生成枚举类和出参入参类可方便使用
- 增加了分布式锁
- 支持PHP8原生注解，PHP8.2枚举


## 使用须知

* php版本 >= 8.2，参数映射到PHP类不支持联合类型


## 安装

```
composer require lijiajun9602/hyperf-gen-business

```

## 使用


### 1. 发布配置文件

```bash
php bin/hyperf.php vendor:publish lijiajun9602/hyperf-gen-business
```

#### 1.1 配置信息

> config/autoload/gen-business.php

