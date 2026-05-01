# Implementation Plan: Release 2.0 — Modernize & Harden

## Overview

本计划将 oasis/dynamodb-odm 从 PHP 7.1 / PHPUnit 5.7 全面升级到 PHP 8.5 / PHPUnit 13。采用**先加固再迁移**的策略：

- **Phase 0**：在旧环境（PHP 7.4 + PHPUnit 5.7 + doctrine/annotations）下补齐隔离单元测试，建立覆盖率基线
- **Phase 1**：在测试保护下执行依赖升级、Attribute 迁移、语法升级

编排顺序：旧环境单元测试补齐 → 依赖升级与基础设施搭建 → 测试适配 PHPUnit 13 → Attribute 类迁移 → ItemReflection 解析迁移 → ItemManager 构造函数简化 → PHP 8.x 语法全面升级 → 测试模型迁移与现有集成测试适配 → PBT → 集成测试补齐 → 文档更新 → 手工测试 → Code Review。

## Tasks

- [x] 1. Phase 0: 旧环境单元测试补齐
  - [x] 1.1 编写 Annotation 类单元测试（Field / Item / Index / PartitionedHashKey）
    - 在 `ut/` 根目录下创建 `FieldTest.php`、`ItemTest.php`、`IndexTest.php`、`PartitionedHashKeyTest.php`
    - 使用旧 doctrine 注解 API 和 `PHPUnit_Framework_TestCase`
    - 测试 `Field`：构造参数（通过 doctrine 注解数组格式）、默认值、`CAS_DISABLED`/`CAS_ENABLED`/`CAS_TIMESTAMP` 常量、`gsiKey` 默认 false
    - 测试 `Item`：构造参数、`primaryIndex` 的 array→Index 转换、GSI/LSI 的 array→Index 批量转换、`projected` 默认 false、`repository` 默认 null
    - 测试 `Index`：positional array 构造、named array 构造、`getKeys()` 返回值（仅 hash / hash+range）、`getDynamodbIndex()` 转换、空 hash 抛出 `AnnotationParsingException`
    - 测试 `PartitionedHashKey`：构造参数、`$size` 默认 16、`$hashField` 和 `$baseField` 必填
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 3.5, 3.6, 3.7, 3.8, 10.6_
  - [x] 1.2 编写 `ItemReflection` 单元测试
    - 在 `ut/ItemReflectionTest.php` 创建测试，使用 `PHPUnit_Framework_TestCase`
    - 使用 `AnnotationReader` 创建 Reader 实例传给 `parse(Reader $reader)`
    - 测试 Attribute 解析：使用 `ut/` 下的测试模型类（User、Game 等）验证 `parse()` 正确提取 `Item`、`Field`、`PartitionedHashKey` 注解
    - 测试 hydrate/dehydrate：验证数组↔对象转换
    - 测试属性读写：`getPropertyValue()`、`updateProperty()`
    - 测试主键提取：`getPrimaryKeys()`、`getPrimaryIdentifier()`
    - 测试字段映射：`getFieldNameMapping()`、`getFieldNameByPropertyName()`
    - 测试 PartitionedHashKey 值生成：`getAllPartitionedValues()`
    - 测试缺少 `@Item` 时抛出 `NotAnnotatedException`
    - 测试 `getProjectedAttributes()` 对 projected item（BasicGameInfo）的行为
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 4.3, 4.4, 4.5, 4.6, 4.7, 10.2_
  - [x] 1.3 编写 `ItemManager` 单元测试
    - 在 `ut/ItemManagerUnitTest.php` 创建测试，使用 `PHPUnit_Framework_TestCase`
    - 使用旧 4 参数构造函数：`new ItemManager($dbCnn, $prefix, $cacheDir, $isDev)`
    - 测试 Connection 构造：传入 mock `Connection` 实例，验证 `createDBConnection()` 返回 clone
    - 测试 array 构造：传入数组配置，验证 `getDatabaseConfig()` 返回配置、`createDBConnection()` 返回 `DynamoDbConnection`
    - 测试命名空间扫描：`addNamespace()` 后 `getPossibleItemClasses()` 包含扫描到的类
    - 测试 repository 缓存：多次 `getRepository()` 返回同一实例
    - 测试操作委托：`persist`/`get`/`remove`/`flush`/`detach`/`clear`/`refresh` 委托到 repository
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 5.5, 5.6, 10.5_
  - [x] 1.4 编写 `ManagedItemState` 单元测试
    - 在 `ut/ManagedItemStateTest.php` 创建测试，使用 `PHPUnit_Framework_TestCase`
    - 测试脏数据检测：未修改对象 `hasDirtyData()` 返回 false，修改后返回 true
    - 测试 CAS 时间戳更新：`updateCASTimestamps()` 后 CAS timestamp 字段被更新
    - 测试 PartitionedHashKey 更新：`updatePartitionedHashKeys()` 后分区键被正确计算
    - 测试 check condition 生成：`getCheckConditionData()` 返回原始 CAS 字段值
    - 测试状态转换：NEW → MANAGED → REMOVED
    - 测试 `isNew()`、`isRemoved()` 在各状态下的返回值
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 10.3_
  - [x] 1.5 编写 `ItemRepository` 单元测试
    - 在 `ut/ItemRepositoryTest.php` 创建测试，使用 `PHPUnit_Framework_TestCase`
    - mock `Connection` 接口隔离 DynamoDB
    - 测试 persist：新对象 persist 后 flush 调用 `Connection::set()` 或 `Connection::batchPut()`
    - 测试 get：mock `Connection::get()` 返回数据，验证 hydrate 后的对象
    - 测试 remove：remove 后 flush 调用 `Connection::batchDelete()`
    - 测试 flush：验证 CAS 和非 CAS 路径
    - 测试 detach：detach 后修改不触发 flush
    - 测试 refresh：验证对象数据被刷新
    - 测试 query 字段解析：`getFieldsArray()` 正确映射 `#field` 到 attribute name
    - 测试 batch 操作：`batchGet()` 正确转换 keys 并返回 hydrated 对象
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 10.1, 10.4_
  - [x] 1.6 编写 Console Commands 和 ConsoleHelper 单元测试
    - 在 `ut/` 根目录下创建 `CreateSchemaCommandTest.php`、`UpdateSchemaCommandTest.php`、`DropSchemaCommandTest.php`、`ConsoleHelperTest.php`
    - 使用 `PHPUnit_Framework_TestCase`
    - 测试命令配置：name、description、options
    - 测试 `getManagedItemClasses()` 的类过滤逻辑（跳过 `NotAnnotatedException`、`ReflectionException`）
    - 测试 `ConsoleHelper::addCommands()` 注册三个命令
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 10.7, 10.8_
  - [x] 1.7 编写异常类继承关系测试
    - 在 `ut/ExceptionHierarchyTest.php` 创建测试，使用 `PHPUnit_Framework_TestCase`
    - 验证 `ODMException extends RuntimeException`
    - 验证 `AnnotationParsingException extends ODMException`
    - 验证 `NotAnnotatedException extends ODMException`
    - 验证 `UnderlyingDatabaseException extends ODMException`
    - 验证 `DataConsistencyException extends RuntimeException`（非 ODMException）
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 10.9_
  - [x] 1.8 补充无效参数异常测试
    - 在各测试文件中补充异常路径测试
    - 测试 `ItemReflection::dehydrate()` 传入非对象时抛出 `ODMException`
    - 测试 `ItemReflection::hydrate()` 传入错误类型对象时抛出 `ODMException`
    - 测试 `ItemRepository::persist()` 传入错误类型对象时抛出 `ODMException`
    - 测试 `ItemRepository::get()` 传入未定义字段时抛出 `ODMException`
    - 使用 `php74 vendor/bin/phpunit` 运行验证通过
    - _Requirements: 10.10_
  - [x] 1.9 验证覆盖率基线
    - 使用 `php74 vendor/bin/phpunit` 运行全部新增单元测试
    - 确认所有测试通过，记录覆盖率基线数据
    - _Requirements: 12.1_
  - [x] 1.10 Checkpoint: 验证旧环境下全部单元测试通过（`php74 vendor/bin/phpunit`），commit

- [-] 2. 依赖升级与测试基础设施搭建
  - [x] 2.1 更新 `composer.json` 依赖版本
    - 将 `php` 改为 `>=8.5`
    - 将 `phpunit/phpunit` 改为 `^13.0`
    - 将 `symfony/console` 改为 `^7.0`，`symfony/finder` 改为 `^7.0`
    - 升级 `oasis/aws-wrappers` 和 `oasis/logging` 到最新兼容版本
    - 移除 `doctrine/annotations` 和 `doctrine/common`
    - 在 `require-dev` 中新增 `giorgiosironi/eris: ^1.0`
    - 运行 `composer update` 确认依赖解析无冲突
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8_
  - [x] 2.2 更新 `phpunit.xml` 为 PHPUnit 13 schema 并配置三个 test suite
    - 使用 PHPUnit 13 兼容的 XML schema
    - 定义 `unit` suite 指向 `ut/Unit/`，`integration` suite 指向 `ut/Integration/`，`pbt` suite 指向 `ut/PBT/`
    - 配置 `<source>` 覆盖率包含 `src/` 目录
    - 创建 `ut/Unit/`、`ut/Integration/`、`ut/PBT/` 目录（含 `.gitkeep`）
    - 创建 `ut/Unit/Attributes/`、`ut/Unit/Console/Commands/`、`ut/Unit/Exceptions/` 子目录
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.8_
  - [-] 2.3 Checkpoint: 验证 `composer install` 成功且 `vendor/bin/phpunit --version` 报告 13.x，commit

- [~] 3. 测试适配 PHPUnit 13
  - [ ] 3.1 将 Phase 0 的单元测试迁移到 `ut/Unit/` 目录并适配 PHPUnit 13 API
    - 将 `ut/FieldTest.php` → `ut/Unit/Attributes/FieldTest.php`
    - 将 `ut/ItemTest.php` → `ut/Unit/Attributes/ItemTest.php`
    - 将 `ut/IndexTest.php` → `ut/Unit/Attributes/IndexTest.php`
    - 将 `ut/PartitionedHashKeyTest.php` → `ut/Unit/Attributes/PartitionedHashKeyTest.php`
    - 将 `ut/ItemReflectionTest.php` → `ut/Unit/ItemReflectionTest.php`
    - 将 `ut/ItemManagerUnitTest.php` → `ut/Unit/ItemManagerTest.php`
    - 将 `ut/ManagedItemStateTest.php` → `ut/Unit/ManagedItemStateTest.php`
    - 将 `ut/ItemRepositoryTest.php` → `ut/Unit/ItemRepositoryTest.php`
    - 将 `ut/CreateSchemaCommandTest.php` → `ut/Unit/Console/Commands/CreateSchemaCommandTest.php`
    - 将 `ut/UpdateSchemaCommandTest.php` → `ut/Unit/Console/Commands/UpdateSchemaCommandTest.php`
    - 将 `ut/DropSchemaCommandTest.php` → `ut/Unit/Console/Commands/DropSchemaCommandTest.php`
    - 将 `ut/ConsoleHelperTest.php` → `ut/Unit/Console/ConsoleHelperTest.php`
    - 将 `ut/ExceptionHierarchyTest.php` → `ut/Unit/Exceptions/ExceptionHierarchyTest.php`
    - 所有测试类：`PHPUnit_Framework_TestCase` → `PHPUnit\Framework\TestCase`
    - 所有 `setUp()` / `tearDown()` 方法添加 `: void` 返回类型
    - 适配 PHPUnit 13 的断言 API 变更（如有）
    - **注意**：此时 `ItemReflectionTest` 仍使用旧 `parse(Reader $reader)` API，`ItemManagerTest` 仍使用旧 4 参数构造函数——这些将在后续 Task 5、6 中随源码迁移一起适配
    - _Requirements: 8.1, 8.2_
  - [ ] 3.2 运行 `vendor/bin/phpunit --testsuite unit` 验证迁移后的测试全部通过
    - 确认所有单元测试在 PHPUnit 13 下通过
    - _Requirements: 9.5_
  - [ ] 3.3 Checkpoint: 验证全部单元测试在 PHPUnit 13 下通过，commit

- [ ] 4. Attribute 类迁移（目录重命名 + 四个类改写）
  - [ ] 4.1 适配 Attribute 单元测试到新 API（RED）
    - 更新 `ut/Unit/Attributes/FieldTest.php`：测试 constructor promotion 风格的 `Field`（`new Field(name: 'uid', type: 'number')`）
    - 更新 `ut/Unit/Attributes/ItemTest.php`：测试 `readonly` 属性、`array|Index` 兼容处理
    - 更新 `ut/Unit/Attributes/IndexTest.php`：测试 constructor promotion 风格（`new Index(hash: 'id')`），移除 positional array 构造测试
    - 更新 `ut/Unit/Attributes/PartitionedHashKeyTest.php`：测试 constructor promotion 风格
    - 更新 import 从 `Oasis\Mlib\ODM\Dynamodb\Annotations\*` 到 `Oasis\Mlib\ODM\Dynamodb\Attributes\*`
    - 此时测试应 **失败**（RED），因为 Attribute 类尚未迁移
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 10.6_
  - [ ] 4.2 使用 `git mv src/Annotations src/Attributes` 重命名目录，更新命名空间和所有引用
    - 执行 `git mv src/Annotations src/Attributes`
    - 将四个类的 `namespace` 从 `Oasis\Mlib\ODM\Dynamodb\Annotations` 改为 `Oasis\Mlib\ODM\Dynamodb\Attributes`
    - 更新所有 `src/` 文件中的 `use Oasis\Mlib\ODM\Dynamodb\Annotations\*` 为 `use Oasis\Mlib\ODM\Dynamodb\Attributes\*`（涉及 `ItemReflection.php`、`ManagedItemState.php`、`DynamoDbSchemaTool.php`、`AbstractSchemaCommand.php`）
    - 移除所有 `Doctrine\Common\Annotations` 引用
    - _Requirements: 3.9, 3.10_
  - [ ] 4.3 改写 `Field` 类为 PHP 8 Attribute
    - 添加 `#[Attribute(Attribute::TARGET_PROPERTY)]`
    - 使用 constructor promotion 声明属性：`?string $name = null`、`string $type = 'string'`、`string $cas = self::CAS_DISABLED`、`bool $gsiKey = false`
    - 保留 `CAS_DISABLED`、`CAS_ENABLED`、`CAS_TIMESTAMP` 常量
    - _Requirements: 3.1, 3.5_
  - [ ] 4.4 改写 `Item` 类为 PHP 8 Attribute
    - 添加 `#[Attribute(Attribute::TARGET_CLASS)]`
    - 属性声明为 `readonly`
    - 构造函数保留 `array|Index` 兼容处理，使用 `array_map` + 箭头函数转换 GSI/LSI
    - _Requirements: 3.2, 3.6, 6.4_
  - [ ] 4.5 改写 `Index` 类（不声明 `#[Attribute]`，作为嵌套参数）
    - 使用 constructor promotion：`string $hash = ''`、`string $range = ''`、`string $name = ''`
    - 空 hash 时抛出 `AnnotationParsingException`
    - 保留 `getKeys()` 和 `getDynamodbIndex()` 方法
    - _Requirements: 3.3, 3.8_
  - [ ] 4.6 改写 `PartitionedHashKey` 类为 PHP 8 Attribute
    - 添加 `#[Attribute(Attribute::TARGET_PROPERTY)]`
    - 使用 constructor promotion：`string $hashField`、`string $baseField`、`int $size = 16`
    - _Requirements: 3.4, 3.7_
  - [ ] 4.7 运行 Attribute 单元测试验证全部通过（GREEN）
    - 执行 `vendor/bin/phpunit --testsuite unit --filter Attributes`
    - 确认所有 Attribute 测试从 RED 变为 GREEN
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 10.6_
  - [ ] 4.8 Checkpoint: 验证 Attribute 单元测试全部通过，commit

- [ ] 5. ItemReflection 解析迁移
  - [ ] 5.1 适配 `ItemReflection` 单元测试到新 API（RED）
    - 更新 `ut/Unit/ItemReflectionTest.php`：移除 `AnnotationReader` / `Reader` 相关代码
    - 调用 `parse()` 时不传参数（新签名 `parse(): void`）
    - 更新 import 从 `Annotations\*` 到 `Attributes\*`
    - **注意**：此时测试模型类（User、Game 等）仍使用旧 docblock 注解，需要在测试中使用已迁移到 Attribute 的临时测试模型，或在此 task 中先将测试所用的模型类迁移到 Attribute 语法
    - 此时测试应 **失败**（RED），因为 `parse()` 仍接受 `Reader` 参数
    - _Requirements: 3.11, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 10.2_
  - [ ] 5.2 重写 `ItemReflection::parse()` 方法
    - 移除 `Reader $reader` 参数，改为无参数 `parse(): void`
    - 使用 `ReflectionClass::getAttributes(Item::class)` 读取类级 Attribute
    - 使用 `ReflectionProperty::getAttributes(Field::class)` 和 `getAttributes(PartitionedHashKey::class)` 读取属性级 Attribute
    - 通过 `$attr->newInstance()` 实例化 Attribute 对象
    - 移除所有 `Doctrine\Common\Annotations\Reader` 引用
    - 更新 import 从 `Annotations\*` 到 `Attributes\*`
    - _Requirements: 3.11, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8_
  - [ ] 5.3 运行 `ItemReflection` 单元测试验证全部通过（GREEN）
    - 执行 `vendor/bin/phpunit --testsuite unit --filter ItemReflectionTest`
    - 确认所有测试从 RED 变为 GREEN
    - _Requirements: 4.1, 4.2, 10.2_
  - [ ] 5.4 Checkpoint: 验证 ItemReflection 单元测试全部通过，commit

- [ ] 6. ItemManager 构造函数简化
  - [ ] 6.1 适配 `ItemManager` 单元测试到新 API（RED）
    - 更新 `ut/Unit/ItemManagerTest.php`：构造 `ItemManager` 时仅传 2 参数（`$dbCnn` 和 `$prefix`）
    - 移除对 `getReader()` 和 `loadAnnotationClass()` 的测试
    - 新增测试 `getItemReflection()` 调用 `parse()` 无参数
    - 此时测试应 **失败**（RED），因为构造函数仍为 4 参数
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 10.5_
  - [ ] 6.2 简化 `ItemManager::__construct()` 为 2 参数
    - 移除 `$cacheDir` 和 `$isDev` 参数
    - 移除 `AnnotationRegistry::registerLoader()`、`CachedReader`、`AnnotationReader`、`FilesystemCache` 相关代码
    - 移除 `$reader` 属性、`getReader()` 方法、`loadAnnotationClass()` 方法
    - 移除所有 `Doctrine\Common\Annotations` 和 `Doctrine\Common\Cache` import
    - `getItemReflection()` 调用 `$reflection->parse()` 不传参数
    - 使用 constructor promotion 和 union type：`array|Connection $dbCnn`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_
  - [ ] 6.3 运行 `ItemManager` 单元测试验证全部通过（GREEN）
    - 执行 `vendor/bin/phpunit --testsuite unit --filter ItemManagerTest`
    - 确认所有测试从 RED 变为 GREEN
    - _Requirements: 5.1, 5.2, 10.5_
  - [ ] 6.4 Checkpoint: 验证 ItemManager 单元测试全部通过，commit

- [ ] 7. PHP 8.x 语法全面升级
  - [ ] 7.1 升级 `ItemReflection.php` 语法
    - 添加 typed properties：`string $itemClass`、`?ReflectionClass $reflectionClass`、`?Item $itemDefinition` 等
    - 移除 `getPropertyValue()` 和 `updateProperty()` 中的 `setAccessible(true)` 调用
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.6, 6.7, 6.8_
  - [ ] 7.2 升级 `ItemRepository.php` 语法
    - 添加 typed properties
    - 使用 constructor promotion：`$itemReflection`、`$itemManager`
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.2, 6.7, 6.8_
  - [ ] 7.3 升级 `ManagedItemState.php` 语法
    - 添加 typed properties
    - 使用 constructor promotion：`$itemReflection`、`$item`、`$originalData`
    - 将 `isDataEqual()` 中的 `switch(true)` 替换为 `match(true)`（CR Q4 决策）
    - 更新 import 从 `Annotations\Field` 到 `Attributes\Field`
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.2, 6.3, 6.7, 6.8_
  - [ ] 7.4 升级 `Connection.php` 接口
    - 添加所有方法的返回类型声明（`__construct` 除外，Non-Goal 约束）
    - _Requirements: 6.7, 6.8_
  - [ ] 7.5 升级 `AbstractDbConnection.php` 语法
    - 添加 typed properties：`array $dbConfig`、`string $tableName`、`array $attributeTypes`、`?ItemReflection $itemReflection`
    - 添加所有方法的返回类型声明
    - `protected` 方法签名与子类同步收紧（CR Q1 决策）
    - _Requirements: 6.1, 6.7, 6.8_
  - [ ] 7.6 升级 `DynamoDbConnection.php` 语法
    - 添加 typed property：`?DynamoDbTable $dynamodbTable`
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.7, 6.8_
  - [ ] 7.7 升级 `AbstractSchemaTool.php` 语法
    - 添加 typed properties
    - 使用 constructor promotion：`$itemManager`、`$classReflections`、`$outputFunction`
    - 添加所有方法的返回类型声明
    - `protected` 方法签名与子类同步收紧（CR Q1 决策）
    - _Requirements: 6.1, 6.2, 6.7, 6.8_
  - [ ] 7.8 升级 `DynamoDbSchemaTool.php` 语法
    - 添加返回类型声明：`createSchema(): void`、`updateSchema(): void`、`dropSchema(): void`
    - 更新 import 从 `Annotations\Index` 到 `Attributes\Index`
    - 如有 `strpos` 模式则替换为 `str_contains`
    - _Requirements: 6.5, 6.7, 6.8_
  - [ ] 7.9 升级 Console 层语法
    - `AbstractSchemaCommand.php`：添加 typed property `?ItemManager $itemManager`，添加返回类型声明
    - `CreateSchemaCommand.php`、`UpdateSchemaCommand.php`、`DropSchemaCommand.php`：`configure(): void`、`execute(): int`（Symfony 7 要求返回 int）
    - `ConsoleHelper.php`：使用 constructor promotion，添加返回类型声明
    - _Requirements: 6.1, 6.2, 6.7, 6.8_
  - [ ] 7.10 运行全部单元测试验证语法升级无回归
    - 执行 `vendor/bin/phpunit --testsuite unit`
    - 确认所有单元测试通过
    - _Requirements: 6.8_
  - [ ] 7.11 Checkpoint: 验证项目无语法错误（`php -l` 检查所有 src/ 文件）且单元测试全部通过，commit

- [ ] 8. 测试模型 Attribute 迁移与现有集成测试适配
  - [ ] 8.1 迁移 `ut/` 下五个测试模型类到 PHP 8 原生 Attribute
    - `User.php`：将 `@Item(...)`、`@Field(...)`、`@Index(...)`、`@PartitionedHashKey(...)` docblock 注解替换为 `#[Item(...)]`、`#[Field(...)]`、`#[Index(...)]`、`#[PartitionedHashKey(...)]` Attribute
    - `Game.php`：同上
    - `ConsoleGame.php`：同上
    - `BasicGameInfo.php`：同上
    - `CasDemoUser.php`：同上
    - 所有 import 从 `Oasis\Mlib\ODM\Dynamodb\Annotations\*` 改为 `Oasis\Mlib\ODM\Dynamodb\Attributes\*`
    - 移除所有 `@Item`、`@Field`、`@Index`、`@PartitionedHashKey` docblock 注解
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8_
  - [ ] 8.2 适配 `ut/ItemManagerTest.php` 到 PHPUnit 13 并迁移到 `ut/Integration/`
    - 将文件移动到 `ut/Integration/ItemManagerTest.php`
    - `PHPUnit_Framework_TestCase` → `PHPUnit\Framework\TestCase`
    - `setUp()` 添加 `: void` 返回类型
    - 构造 `ItemManager` 时移除 `$cacheDir` 和 `$isDev` 参数（仅传 `$dbCnn` 和 `$prefix`）
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 13.10, 13.11_
  - [ ] 8.3 运行全部单元测试验证测试模型迁移无回归
    - 执行 `vendor/bin/phpunit --testsuite unit`
    - 确认所有单元测试通过（单元测试中使用测试模型类的部分应正常工作）
    - _Requirements: 7.6, 7.7, 7.8_
  - [ ] 8.4 Checkpoint: 验证测试模型类语法正确且单元测试全部通过，commit

- [ ] 9. Property-Based Testing（PBT）
  - [ ] 9.1 编写 Property 1: Hydrate/Dehydrate Round-Trip 测试
    - 在 `ut/PBT/HydrateDehydrateRoundTripTest.php` 创建测试
    - 使用 Eris 生成覆盖 DynamoDB 全部数据类型（S/N/B/BOOL/NULL/L/M/SS/NS/BS）的 field data 数组
    - 验证 `dehydrate(hydrate(data))` 产生与原始数据等价的数组（考虑 null/空字符串等价性）
    - 注释引用：`// Feature: release-2.0, Property 1: Hydrate/Dehydrate Round-Trip`
    - **Property 1: Hydrate/Dehydrate Round-Trip**
    - **Validates: Requirements 11.2**
  - [ ] 9.2 编写 Property 2: Dirty Data Detection Idempotence 测试
    - 在 `ut/PBT/DirtyDataIdempotenceTest.php` 创建测试
    - 复用 Property 1 的数据生成器
    - 验证未修改对象的 `hasDirtyData()` 返回 false
    - 注释引用：`// Feature: release-2.0, Property 2: Dirty Data Detection Idempotence`
    - **Property 2: Dirty Data Detection Idempotence**
    - **Validates: Requirements 11.3**
  - [ ] 9.3 编写 Property 3: CAS Check Condition Invariant 测试
    - 在 `ut/PBT/CasCheckConditionTest.php` 创建测试
    - 生成包含 CAS 字段的原始数据
    - 验证 `getCheckConditionData()` 返回原始 CAS 字段值
    - 注释引用：`// Feature: release-2.0, Property 3: CAS Check Condition Invariant`
    - **Property 3: CAS Check Condition Invariant**
    - **Validates: Requirements 11.4**
  - [ ] 9.4 编写 Property 4: PartitionedHashKey Computation Determinism 测试
    - 在 `ut/PBT/PartitionedHashKeyTest.php` 创建测试
    - 生成随机字符串作为 base value 和 hash source
    - 验证两次计算产生相同结果
    - 注释引用：`// Feature: release-2.0, Property 4: PartitionedHashKey Computation Determinism`
    - **Property 4: PartitionedHashKey Computation Determinism**
    - **Validates: Requirements 11.5**
  - [ ] 9.5 编写 Property 5: Index Keys Consistency 测试
    - 在 `ut/PBT/IndexKeysConsistencyTest.php` 创建测试
    - 生成随机 hash/range 字符串
    - 验证 `getKeys()` 返回正确数量的元素（1 或 2）
    - 注释引用：`// Feature: release-2.0, Property 5: Index Keys Consistency`
    - **Property 5: Index Keys Consistency**
    - **Validates: Requirements 11.6**
  - [ ] 9.6 编写 Property 6: Partitioned Values Output Size 测试
    - 在 `ut/PBT/PartitionedValuesTest.php` 创建测试
    - 生成随机正整数（partition size）和字符串（base value）
    - 验证 `getAllPartitionedValues()` 返回恰好 `size` 个元素
    - 注释引用：`// Feature: release-2.0, Property 6: Partitioned Values Output Size`
    - **Property 6: Partitioned Values Output Size**
    - **Validates: Requirements 11.7**
  - [ ] 9.7 运行全部 PBT 测试验证通过
    - 执行 `vendor/bin/phpunit --testsuite pbt`
    - 确认所有 PBT 测试通过
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7_
  - [ ] 9.8 Checkpoint: 验证全部 PBT 测试通过，检查 unit+pbt 覆盖率 ≥85%，commit

- [ ] 10. 集成测试补齐
  - [ ] 10.1 编写 CRUD 生命周期集成测试
    - 在 `ut/Integration/` 下补齐集成测试（可在现有 `ItemManagerTest.php` 中扩展或新建文件）
    - 覆盖完整 CRUD 生命周期：create（persist + flush）→ read（get）→ update（modify + flush）→ delete（remove + flush）
    - _Requirements: 13.1_
  - [ ] 10.2 编写 Query、Scan、BatchGet 集成测试
    - 测试 Query 操作：使用 GSI 和 filter expressions
    - 测试 Scan 操作：包括 parallel scan
    - 测试 BatchGet 操作
    - _Requirements: 13.2, 13.3, 13.4_
  - [ ] 10.3 编写 CAS 相关集成测试
    - 测试 CAS 冲突检测：两个 ItemManager 并发修改同一 item，flush 抛出 `DataConsistencyException`
    - 测试 CAS 时间戳自动更新行为
    - _Requirements: 13.5, 13.6_
  - [ ] 10.4 编写 PartitionedHashKey 和 Projected Item 集成测试
    - 测试 PartitionedHashKey 的 multi-query 操作
    - 测试 projected Item 只读强制（修改后 flush 抛出 `ODMException`）
    - _Requirements: 13.7, 13.8_
  - [ ] 10.5 编写 Schema 工具集成测试
    - 测试 `DynamoDbSchemaTool` 的 create、update、drop 操作
    - _Requirements: 13.9_
  - [ ] 10.6 运行全部集成测试验证通过
    - 执行 `vendor/bin/phpunit --testsuite integration`
    - 确认所有集成测试通过（需有效 DynamoDB 环境）
    - 检查集成测试覆盖率 ≥75%
    - _Requirements: 8.4, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 12.2_
  - [ ] 10.7 Checkpoint: 验证全部集成测试通过，commit

- [ ] 11. 文档更新
  - [ ] 11.1 更新 `PROJECT.md`
    - PHP ≥8.5、PHPUnit ^13.0
    - `symfony/console` ^7.0、`symfony/finder` ^7.0
    - 移除 `doctrine/annotations` 和 `doctrine/common` 的记录
    - 新增 `giorgiosironi/eris` ^1.0（dev）
    - 更新测试命令：`--testsuite unit`、`--testsuite integration`、`--testsuite pbt`
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6_
  - [ ] 11.2 更新 `docs/state/architecture.md`
    - 注解体系 section：从 docblock 注解更新为 PHP 8 原生 Attribute，命名空间从 `Annotations` 改为 `Attributes`
    - 核心类职责 — `ItemManager`：构造函数描述从 4 参数改为 2 参数
    - 核心类职责 — `ItemReflection`：解析方式从 `Reader` 改为 `ReflectionAPI`
    - DBAL 抽象 section：`ItemManager` 构造时不再接受 `$cacheDir`/`$isDev`
    - _Requirements: 14.7, 14.8, 14.9_
  - [ ] 11.3 Checkpoint: 验证文档内容准确，commit

- [ ] 12. 手工测试
  - [ ] 12.1 Increment alpha tag
    - 查询已有 `v2.0.0-alpha.*` tag，取最大序号 +1，打新 tag（如 `v2.0.0-alpha.1`）
  - [ ] 12.2 验证 `composer install` 在 PHP 8.5 环境下成功
    - 确认无 deprecation warnings 和 abandoned package 警告
    - _Requirements: 1.3, 2.8_
  - [ ] 12.3 验证 `vendor/bin/phpunit --version` 报告 13.x
    - _Requirements: 1.4_
  - [ ] 12.4 验证三个 test suite 可独立运行
    - `vendor/bin/phpunit --testsuite unit`
    - `vendor/bin/phpunit --testsuite pbt`
    - `vendor/bin/phpunit --testsuite integration`（需 DynamoDB 环境）
    - _Requirements: 9.5, 9.6, 9.7_
  - [ ] 12.5 验证覆盖率目标
    - unit + pbt 行覆盖率 ≥85%
    - integration 行覆盖率 ≥75%
    - _Requirements: 12.1, 12.2, 12.3_
  - [ ] 12.6 Checkpoint: 手工测试全部通过，commit

- [ ] 13. Code Review
  - [ ] 13.1 委托给 code-reviewer sub-agent 对本 release 分支的全部变更执行 Code Review，发现问题直接修复
  - [ ] 13.2 Checkpoint: Code Review 完成，所有问题已修复，commit

## Issues

（stabilize 阶段新发现的 issue 记录于此，初始为空）

## Notes

- 执行时须遵循 `spec-execution.md` 中的规范
- **Commit 时机**：commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task（Checkpoint）包含 commit 动作
- **Phase 0 测试策略**：Task 1 在旧环境（PHP 7.4 + PHPUnit 5.7 + doctrine/annotations）下编写单元测试，使用 `php74 vendor/bin/phpunit` 运行。这些测试使用旧 API（`Reader` 参数、4 参数构造函数、docblock 注解），放在 `ut/` 根目录下（因为旧 `phpunit.xml` 只认 `ut/` 目录），文件名以 `*Test.php` 结尾。Phase 0 的测试不是 RED→GREEN（旧代码已存在，测试应直接通过）
- **Phase 1 TDD 编排**：Task 4-6 采用 RED → GREEN 模式。先适配测试到新 API（RED，因为源码尚未迁移），再改实现（GREEN，测试通过）。对于基础设施类 task（Task 2：依赖升级、phpunit.xml）不需要 TDD，直接实现
- **测试迁移**：Task 3 将 Phase 0 写的测试从 `ut/` 根目录迁移到 `ut/Unit/` 子目录，并适配 PHPUnit 13 API。此时测试仍使用旧源码 API（`Reader` 参数等），后续 Task 5、6 中随源码迁移一起适配
- **目录重命名**：Task 4.2 使用 `git mv src/Annotations src/Attributes` 保留 git 历史，在同一 task 内完成所有引用更新
- **`match(true)` 替换**：Task 7.3 中 `ManagedItemState::isDataEqual()` 的 `switch(true)` 替换为 `match(true)`（Design CR Q4 决策）
- **集成测试环境**：Task 10 和手工测试 Task 12.4 需要有效的 AWS DynamoDB 环境和凭证
- **覆盖率驱动**：使用 Xdebug 或 PCOV 作为覆盖率驱动
- **Release 分支**：当前在 `release/2.0` 分支上工作

## Socratic Review

### Q1: 是否覆盖了 requirements 中的全部 14 条 Requirement？

**A:** 是。逐条对应：
- Requirement 1（PHP/PHPUnit 版本）→ Task 2.1, 12.2, 12.3
- Requirement 2（Composer 依赖）→ Task 2.1
- Requirement 3（Attribute 迁移）→ Task 4.1-4.8
- Requirement 4（ItemReflection 解析迁移）→ Task 5.1-5.4
- Requirement 5（ItemManager 简化）→ Task 6.1-6.4
- Requirement 6（PHP 8.x 语法升级）→ Task 7.1-7.11
- Requirement 7（测试模型迁移）→ Task 8.1
- Requirement 8（PHPUnit 13 适配）→ Task 3.1, 8.2
- Requirement 9（phpunit.xml 配置）→ Task 2.2, 12.4
- Requirement 10（单元测试）→ Task 1.1-1.8（Phase 0 编写）, 3.1（PHPUnit 13 适配）, 4.1, 5.1, 6.1（TDD 适配）
- Requirement 11（PBT）→ Task 9.1-9.7
- Requirement 12（覆盖率）→ Task 1.9, 9.8, 10.6, 12.5
- Requirement 13（集成测试）→ Task 8.2, 10.1-10.7
- Requirement 14（文档更新）→ Task 11.1-11.3

### Q2: Design CR 的四个决策是否在 tasks 中体现？

**A:** 是。
- Q1（按变更类型拆分）→ Task 1-11 的整体编排维度
- Q2（TDD 编排）→ Task 4（RED: 4.1 → GREEN: 4.7）、Task 5（RED: 5.1 → GREEN: 5.3）、Task 6（RED: 6.1 → GREEN: 6.3）
- Q3（`git mv` 保留历史）→ Task 4.2 明确使用 `git mv`
- Q4（`match(true)` 替换）→ Task 7.3 明确替换 `switch(true)` 为 `match(true)`

### Q3: Requirements CR 的四个决策是否在 tasks 中体现？

**A:** 是。
- Q1（抽象类同步升级）→ Task 7.5（AbstractDbConnection）和 7.7（AbstractSchemaTool）明确 `protected` 方法签名与子类同步收紧
- Q2（覆盖 DynamoDB 全部数据类型）→ Task 9.1 明确生成覆盖 S/N/B/BOOL/NULL/L/M/SS/NS/BS 的数据
- Q3（`ut/` 下新增子目录不违反 Non-Goals）→ Task 2.2 创建 `ut/Unit/`、`ut/Integration/`、`ut/PBT/`
- Q4（mock Connection 间接测试 SchemaTool）→ Task 1.6 通过 mock 测试 Console Commands 和 ConsoleHelper

### Q4: Phase 0 → Phase 1 的升级顺序是否正确？

**A:** 是。Task 1（Phase 0）在旧环境下补齐单元测试建立覆盖率基线 → Task 2 升级依赖 → Task 3 将测试迁移到 PHPUnit 13 → Task 4-6 在测试保护下执行 Attribute 迁移、ItemReflection 重写、ItemManager 简化（TDD: RED→GREEN）→ Task 7 语法升级 → Task 8 测试模型迁移。这确保了升级过程中始终有测试网兜着。

### Q5: Phase 0 的测试文件放置和后续迁移是否合理？

**A:** 是。Phase 0 的测试放在 `ut/` 根目录下（与旧 `phpunit.xml` 兼容，使用 `php74 vendor/bin/phpunit` 运行）。Task 3 将这些测试迁移到 `ut/Unit/` 子目录并适配 PHPUnit 13 API。此时测试仍使用旧源码 API，后续 Task 5、6 中随源码迁移一起适配到新 API。

### Q6: TDD 编排是否正确（RED → GREEN）？

**A:** 是。Phase 1 的 Task 4-6 严格遵循 TDD：
- Task 4：4.1 适配测试到新 API（RED）→ 4.2-4.6 实现 → 4.7 验证通过（GREEN）
- Task 5：5.1 适配测试到新 API（RED）→ 5.2 实现 → 5.3 验证通过（GREEN）
- Task 6：6.1 适配测试到新 API（RED）→ 6.2 实现 → 6.3 验证通过（GREEN）
- Phase 0（Task 1）不是 TDD，因为旧代码已存在，测试应直接通过
- Task 2（基础设施）不需要 TDD，直接实现
- Task 7（语法升级）是对已有代码的重构，已有测试覆盖

### Q7: Checkpoint 编排是否符合规范？

**A:** 是。每个 top-level task 的最后一个 sub-task 都是 Checkpoint，包含验证步骤和 commit 动作。无独立的 top-level Checkpoint task。

### Q8: 是否存在孤立代码或未集成的步骤？

**A:** 不存在。每个 task 都建立在前一个 task 的基础上：
- Task 1 旧环境补齐测试 → Task 2 升级依赖 → Task 3 迁移测试到 PHPUnit 13 → Task 4 迁移 Attribute 类（依赖 Task 2 的新依赖）→ Task 5 重写解析层（依赖 Task 4 的新 Attribute）→ Task 6 简化构造函数（依赖 Task 5 的无参 parse()）→ Task 7 语法升级 → Task 8 测试模型迁移（依赖 Task 4-6 的新 API）→ Task 9-10 PBT 和集成测试 → Task 11 文档

### Q9: top-level task 结构是否符合规范（1~N 实现 → N+1 手工测试 → 最后 Code Review）？

**A:** 是。Task 1-11 为实现 task，Task 12 为手工测试，Task 13 为 Code Review。

### Q10: Release 分支额外规则是否满足？

**A:** 是。手工测试 task（Task 12）的第一个 sub-task 为 "12.1 Increment alpha tag"。

### Q11: 所有 tasks 是否都是 mandatory（无 optional）？

**A:** 是。所有 sub-task 均使用 `- [ ]` 格式，无 `- [ ]*` optional 标记。

### Q12: 是否存在禁止的 task 类型？

**A:** 不存在。所有 task 都是编写、修改或测试代码的任务。无用户验收测试、部署、性能分析等禁止类型。