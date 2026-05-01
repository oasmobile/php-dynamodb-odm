# Getting Started

oasis/dynamodb-odm 的快速上手指南。

---

## 安装

```bash
composer require oasis/dynamodb-odm
```

确保项目已引入 Composer autoload：

```php
require_once "vendor/autoload.php";
```

---

## 初始化 ItemManager

`ItemManager` 是所有 ODM 操作的入口。

```php
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

$im = new ItemManager(
    ['profile' => 'my-aws-profile', 'region' => 'ap-northeast-1'],  // AWS 配置
    'myapp-',       // 表名前缀
);

$im->addNamespace('App\\Items', __DIR__ . '/src/Items');
```

| 参数 | 说明 |
|------|------|
| `$dbCnn` | AWS SDK 配置数组，或 `Connection` 实例 |
| `$defaultTablePrefix` | 表名前缀，所有表名会自动加上此前缀 |

---

## 定义 Item 类

使用 PHP 8 原生 Attribute 将 PHP 类映射到 DynamoDB 表：

```php
use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;

#[Item(
    table: 'users',
    primaryIndex: new Index(hash: 'id'),
)]
class User
{
    #[Field(type: 'number')]
    protected int $id = 0;

    #[Field(type: 'string')]
    protected ?string $name = null;

    // getter / setter ...
}
```

---

## 基本 CRUD 操作

### 创建

```php
$user = new User();
$user->setId(1);
$user->setName('Alice');
$im->persist($user);
$im->flush();
```

### 读取

```php
$user = $im->get(User::class, ['id' => 1]);
```

### 更新

```php
$user->setName('Bob');
$im->flush();  // 自动检测脏数据并更新
```

### 删除

```php
$im->remove($user);
$im->flush();
```

> `flush()` 是唯一触发数据库写操作的方法。`persist()` 和 `remove()` 只是标记状态。

---

## 查询

### 通过索引查询

```php
$repo = $im->getRepository(User::class);
$users = $repo->query(
    '#class = :class AND #age >= :minAge',
    [':class' => 'A', ':minAge' => 25],
    'class-age-index'
);
```

### 全表扫描

```php
$users = $repo->scan(
    '#name = :name',
    [':name' => 'Alice']
);
```

---

## Schema 管理

配置 `odm-config.php` 后，使用 CLI 工具管理表结构：

```bash
# 创建表
./vendor/bin/oasis-dynamodb-odm odm:schema-tool:create

# 更新表（创建缺失表 + 更新 GSI）
./vendor/bin/oasis-dynamodb-odm odm:schema-tool:update --dry-run

# 删除表
./vendor/bin/oasis-dynamodb-odm odm:schema-tool:drop
```

---

## 进阶主题

- CAS（Check-and-Set）：通过 `#[Field(cas: Field::CAS_TIMESTAMP)]` 实现乐观锁
- PartitionedHashKey：通过分区哈希键提升热点查询性能
- Projected Item：只读投影，仅获取部分属性
- 自定义 Repository：通过 `#[Item(repository: 'App\\MyRepo')]` 指定
- 自定义 Connection：实现 `Connection` 接口以对接其他数据库
