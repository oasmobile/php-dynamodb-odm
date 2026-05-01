# Implementation Plan: Release 2.0 — Modernize & Harden

## Overview

本计划将 oasis/dynamodb-odm 从 PHP 7.1 / PHPUnit 5.7 全面升级到 PHP 8.5 / PHPUnit 13。按变更类型拆分 task，采用 TDD（RED → GREEN）编排：先写测试再写实现。

编排维度：依赖升级与基础设施 → Attribute 类迁移 → ItemReflection 解析迁移 → ItemManager 构造函数简化 → PHP 8.x 语法全面升级 → 测试模型迁移与现有测试适配 → 单元测试 → PBT → 集成测试 → 文档更新 → 手工测试 → Code Review。

## Tasks

- [ ] 1. 依赖升级与测试基础设施搭建
  - [ ] 1.1 更新 `composer.json` 依赖版本
    - 将 `php` 改为 `>=8.5`
    - 将 `phpunit/phpunit` 改为 `^13.0`
    - 将 `symfony/console` 改为 `^7.0`，`symfony/finder` 改为 `^7.0`
    - 升级 `oasis/aws-wrappers` 和 `oasis/logging` 到最新兼容版本
    - 移除 `doctrine/annotations` 和 `doctrine/common`
    - 在 `require-dev` 中新增 `giorgiosironi/eris: ^1.0`
    - 运行 `composer update` 确认依赖解析无冲突
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8_
  - [ ] 1.2 更新 `phpunit.xml` 为 PHPUnit 13 schema 并配置三个 test suite
    - 使用 PHPUnit 13 兼容的 XML schema
    - 定义 `unit` suite 指向 `ut/Unit/`，`integration` suite 指向 `ut/Integration/`，`pbt` suite 指向 `ut/PBT/`
    - 配置 `<source>` 覆盖率包含 `src/` 目录
    - 创建 `ut/Unit/`、`ut/Integration/`、`ut/PBT/` 目录（含 `.gitkeep`）
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.8_
  - [ ] 1.3 Checkpoint: 验证 `composer install` 成功且 `vendor/bin/phpunit --version` 报告 13.x，commit

- [ ] 2. Attribute 类迁移（目录重命名 + 四个类改写）
  - [ ] 2.1 编写 Attribute 类单元测试（RED）
    - 在 `ut/Unit/Attributes/` 下创建 `FieldTest.php`、`ItemTest.php`、`IndexTest.php`、`PartitionedHashKeyTest.php`
    - 测试 `Field`：构造参数、默认值、`CAS_DISABLED`/`CAS_ENABLED`/`CAS_TIMESTAMP` 常量、`gsiKey` 默认 false
    - 测试 `Item`：构造参数、`readonly` 属性、`primaryIndex` 的 array→Index 转换、GSI/LSI 的 array→Index 批量转换、`projected` 默认 false、`repository` 默认 null
    - 测试 `Index`：构造参数、`getKeys()` 返回值（仅 hash / hash+range）、`getDynamodbIndex()` 转换、空 hash 抛出 `AnnotationParsingException`
    - 测试 `PartitionedHashKey`：构造参数、`$size` 默认 16、`$hashField` 和 `$baseField` 必填
    - 此时测试应 **失败**（RED），因为 Attribute 类尚未迁移
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 10.6_
  - [ ] 2.2 使用 `git mv src/Annotations src/Attributes` 重命名目录，更新命名空间和所有引用
    - 执行 `git mv src/Annotations src/Attributes`
    - 将四个类的 `namespace` 从 `Oasis\Mlib\ODM\Dynamodb\Annotations` 改为 `Oasis\Mlib\ODM\Dynamodb\Attributes`
    - 更新所有 `src/` 文件中的 `use Oasis\Mlib\ODM\Dynamodb\Annotations\*` 为 `use Oasis\Mlib\ODM\Dynamodb\Attributes\*`（涉及 `ItemReflection.php`、`ManagedItemState.php`、`DynamoDbSchemaTool.php`、`AbstractSchemaCommand.php`）
    - 移除所有 `Doctrine\Common\Annotations` 引用
    - _Requirements: 3.9, 3.10_
  - [ ] 2.3 改写 `Field` 类为 PHP 8 Attribute
    - 添加 `#[Attribute(Attribute::TARGET_PROPERTY)]`
    - 使用 constructor promotion 声明属性：`?string $name = null`、`string $type = 'string'`、`string $cas = self::CAS_DISABLED`、`bool $gsiKey = false`
    - 保留 `CAS_DISABLED`、`CAS_ENABLED`、`CAS_TIMESTAMP` 常量
    - _Requirements: 3.1, 3.5_
  - [ ] 2.4 改写 `Item` 类为 PHP 8 Attribute
    - 添加 `#[Attribute(Attribute::TARGET_CLASS)]`
    - 属性声明为 `readonly`
    - 构造函数保留 `array|Index` 兼容处理，使用 `array_map` + 箭头函数转换 GSI/LSI
    - _Requirements: 3.2, 3.6, 6.4_
  - [ ] 2.5 改写 `Index` 类（不声明 `#[Attribute]`，作为嵌套参数）
    - 使用 constructor promotion：`string $hash = ''`、`string $range = ''`、`string $name = ''`
    - 空 hash 时抛出 `AnnotationParsingException`
    - 保留 `getKeys()` 和 `getDynamodbIndex()` 方法
    - _Requirements: 3.3, 3.8_
  - [ ] 2.6 改写 `PartitionedHashKey` 类为 PHP 8 Attribute
    - 添加 `#[Attribute(Attribute::TARGET_PROPERTY)]`
    - 使用 constructor promotion：`string $hashField`、`string $baseField`、`int $size = 16`
    - _Requirements: 3.4, 3.7_
  - [ ] 2.7 运行 Attribute 单元测试验证全部通过（GREEN）
    - 执行 `vendor/bin/phpunit --testsuite unit --filter Attributes`
    - 确认所有 Attribute 测试从 RED 变为 GREEN
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 10.6_
  - [ ] 2.8 Checkpoint: 验证 Attribute 单元测试全部通过，commit

- [ ] 3. ItemReflection 解析迁移
  - [ ] 3.1 编写 `ItemReflection` 单元测试（RED）
    - 在 `ut/Unit/ItemReflectionTest.php` 创建测试
    - 测试 Attribute 解析：使用测试模型类验证 `parse()` 正确提取 `Item`、`Field`、`PartitionedHashKey` Attribute
    - 测试 hydrate/dehydrate：验证数组↔对象转换
    - 测试属性读写：`getPropertyValue()`、`updateProperty()`
    - 测试主键提取：`getPrimaryKeys()`、`getPrimaryIdentifier()`
    - 测试字段映射：`getFieldNameMapping()`、`getFieldNameByPropertyName()`
    - 测试 PartitionedHashKey 值生成：`getAllPartitionedValues()`
    - 测试缺少 `#[Item]` 时抛出 `NotAnnotatedException`
    - 测试 `getProjectedAttributes()` 对 projected item 的行为
    - 此时测试应 **失败**（RED），因为 `parse()` 仍接受 `Reader` 参数
    - _Requirements: 3.11, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 10.2_
  - [ ] 3.2 重写 `ItemReflection::parse()` 方法
    - 移除 `Reader $reader` 参数，改为无参数 `parse(): void`
    - 使用 `ReflectionClass::getAttributes(Item::class)` 读取类级 Attribute
    - 使用 `ReflectionProperty::getAttributes(Field::class)` 和 `getAttributes(PartitionedHashKey::class)` 读取属性级 Attribute
    - 通过 `$attr->newInstance()` 实例化 Attribute 对象
    - 移除所有 `Doctrine\Common\Annotations\Reader` 引用
    - 更新 import 从 `Annotations\*` 到 `Attributes\*`
    - _Requirements: 3.11, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8_
  - [ ] 3.3 运行 `ItemReflection` 单元测试验证全部通过（GREEN）
    - 执行 `vendor/bin/phpunit --testsuite unit --filter ItemReflectionTest`
    - 确认所有测试从 RED 变为 GREEN
    - _Requirements: 4.1, 4.2, 10.2_
  - [ ] 3.4 Checkpoint: 验证 ItemReflection 单元测试全部通过，commit

- [ ] 4. ItemManager 构造函数简化
  - [ ] 4.1 编写 `ItemManager` 单元测试（RED）
    - 在 `ut/Unit/ItemManagerTest.php` 创建测试
    - 测试 Connection 构造：传入 mock `Connection` 实例，验证 `createDBConnection()` 返回 clone
    - 测试 array 构造：传入数组配置，验证 `getDatabaseConfig()` 返回配置、`createDBConnection()` 返回 `DynamoDbConnection`
    - 测试命名空间扫描：`addNamespace()` 后 `getPossibleItemClasses()` 包含扫描到的类
    - 测试 repository 缓存：多次 `getRepository()` 返回同一实例
    - 测试操作委托：`persist`/`get`/`remove`/`flush`/`detach`/`clear`/`refresh` 委托到 repository
    - 测试 `getItemReflection()` 调用 `parse()` 无参数
    - 此时测试应 **失败**（RED），因为构造函数仍为 4 参数
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 10.5_
  - [ ] 4.2 简化 `ItemManager::__construct()` 为 2 参数
    - 移除 `$cacheDir` 和 `$isDev` 参数
    - 移除 `AnnotationRegistry::registerLoader()`、`CachedReader`、`AnnotationReader`、`FilesystemCache` 相关代码
    - 移除 `$reader` 属性、`getReader()` 方法、`loadAnnotationClass()` 方法
    - 移除所有 `Doctrine\Common\Annotations` 和 `Doctrine\Common\Cache` import
    - `getItemReflection()` 调用 `$reflection->parse()` 不传参数
    - 使用 constructor promotion 和 union type：`array|Connection $dbCnn`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_
  - [ ] 4.3 运行 `ItemManager` 单元测试验证全部通过（GREEN）
    - 执行 `vendor/bin/phpunit --testsuite unit --filter ItemManagerTest`
    - 确认所有测试从 RED 变为 GREEN
    - _Requirements: 5.1, 5.2, 10.5_
  - [ ] 4.4 Checkpoint: 验证 ItemManager 单元测试全部通过，commit

- [ ] 5. PHP 8.x 语法全面升级（剩余 src/ 文件）
  - [ ] 5.1 升级 `ItemReflection.php` 语法
    - 添加 typed properties：`string $itemClass`、`?ReflectionClass $reflectionClass`、`?Item $itemDefinition` 等
    - 移除 `getPropertyValue()` 和 `updateProperty()` 中的 `setAccessible(true)` 调用
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.6, 6.7, 6.8_
  - [ ] 5.2 升级 `ItemRepository.php` 语法
    - 添加 typed properties
    - 使用 constructor promotion：`$itemReflection`、`$itemManager`
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.2, 6.7, 6.8_
  - [ ] 5.3 升级 `ManagedItemState.php` 语法
    - 添加 typed properties
    - 使用 constructor promotion：`$itemReflection`、`$item`、`$originalData`
    - 将 `isDataEqual()` 中的 `switch(true)` 替换为 `match(true)`（CR Q4 决策）
    - 更新 import 从 `Annotations\Field` 到 `Attributes\Field`
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.2, 6.3, 6.7, 6.8_
  - [ ] 5.4 升级 `Connection.php` 接口
    - 添加所有方法的返回类型声明（`__construct` 除外，Non-Goal 约束）
    - _Requirements: 6.7, 6.8_
  - [ ] 5.5 升级 `AbstractDbConnection.php` 语法
    - 添加 typed properties：`array $dbConfig`、`string $tableName`、`array $attributeTypes`、`?ItemReflection $itemReflection`
    - 添加所有方法的返回类型声明
    - `protected` 方法签名与子类同步收紧（CR Q1 决策）
    - _Requirements: 6.1, 6.7, 6.8_
  - [ ] 5.6 升级 `DynamoDbConnection.php` 语法
    - 添加 typed property：`?DynamoDbTable $dynamodbTable`
    - 添加所有方法的返回类型声明
    - _Requirements: 6.1, 6.7, 6.8_
  - [ ] 5.7 升级 `AbstractSchemaTool.php` 语法
    - 添加 typed properties
    - 使用 constructor promotion：`$itemManager`、`$classReflections`、`$outputFunction`
    - 添加所有方法的返回类型声明
    - `protected` 方法签名与子类同步收紧（CR Q1 决策）
    - _Requirements: 6.1, 6.2, 6.7, 6.8_
  - [ ] 5.8 升级 `DynamoDbSchemaTool.php` 语法
    - 添加返回类型声明：`createSchema(): void`、`updateSchema(): void`、`dropSchema(): void`
    - 更新 import 从 `Annotations\Index` 到 `Attributes\Index`
    - 如有 `strpos` 模式则替换为 `str_contains`
    - _Requirements: 6.5, 6.7, 6.8_
  - [ ] 5.9 升级 Console 层语法
    - `AbstractSchemaCommand.php`：添加 typed property `?ItemManager $itemManager`，添加返回类型声明
    - `CreateSchemaCommand.php`、`UpdateSchemaCommand.php`、`DropSchemaCommand.php`：`configure(): void`、`execute(): int`（Symfony 7 要求返回 int）
    - `ConsoleHelper.php`：使用 constructor promotion，添加返回类型声明
    - _Requirements: 6.1, 6.2, 6.7, 6.8_
  - [ ] 5.10 Checkpoint: 验证项目无语法错误（`php -l` 检查所有 src/ 文件），commit

- [ ] 6. 测试模型 Attribute 迁移与现有测试适配
  - [ ] 6.1 迁移 `ut/` 下五个测试模型类到 PHP 8 原生 Attribute
    - `User.php`：将 `@Item(...)`、`@Field(...)`、`@Index(...)`、`@PartitionedHashKey(...)` docblock 注解替换为 `#[Item(...)]`、`#[Field(...)]`、`#[Index(...)]`、`#[PartitionedHashKey(...)]` Attribute
    - `Game.php`：同上
    - `ConsoleGame.php`：同上
    - `BasicGameInfo.php`：同上
    - `CasDemoUser.php`：同上
    - 所有 import 从 `Oasis\Mlib\ODM\Dynamodb\Annotations\*` 改为 `Oasis\Mlib\ODM\Dynamodb\Attributes\*`
    - 移除所有 `@Item`、`@Field`、`@Index`、`@PartitionedHashKey` docblock 注解
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8_
  - [ ] 6.2 适配 `ut/ItemManagerTest.php` 到 PHPUnit 13 并迁移到 `ut/Integration/`
    - 将文件移动到 `ut/Integration/ItemManagerTest.php`
    - `PHPUnit_Framework_TestCase` → `PHPUnit\Framework\TestCase`
    - `setUp()` 添加 `: void` 返回类型
    - 构造 `ItemManager` 时移除 `$cacheDir` 和 `$isDev` 参数（仅传 `$dbCnn` 和 `$prefix`）
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 13.10, 13.11_
  - [ ] 6.3 Checkpoint: 验证测试模型类语法正确（`php -l` 检查），commit

- [ ] 7. 补齐隔离单元测试
  - [ ] 7.1 编写 `ManagedItemState` 单元测试
    - 在 `ut/Unit/ManagedItemStateTest.php` 创建测试
    - 测试脏数据检测：未修改对象 `hasDirtyData()` 返回 false，修改后返回 true
    - 测试 CAS 时间戳更新：`updateCASTimestamps()` 后 CAS timestamp 字段被更新
    - 测试 PartitionedHashKey 更新：`updatePartitionedHashKeys()` 后分区键被正确计算
    - 测试 check condition 生成：`getCheckConditionData()` 返回原始 CAS 字段值
    - 测试状态转换：NEW → MANAGED → REMOVED
    - 测试 `isNew()`、`isRemoved()` 在各状态下的返回值
    - _Requirements: 10.3_
  - [ ] 7.2 编写 `ItemRepository` 单元测试
    - 在 `ut/Unit/ItemRepositoryTest.php` 创建测试
    - mock `Connection` 接口隔离 DynamoDB
    - 测试 persist：新对象 persist 后 flush 调用 `Connection::set()` 或 `Connection::batchPut()`
    - 测试 get：mock `Connection::get()` 返回数据，验证 hydrate 后的对象
    - 测试 remove：remove 后 flush 调用 `Connection::batchDelete()`
    - 测试 flush：验证 CAS 和非 CAS 路径
    - 测试 detach：detach 后修改不触发 flush
    - 测试 refresh：验证对象数据被刷新
    - 测试 query 字段解析：`getFieldsArray()` 正确映射 `#field` 到 attribute name
    - 测试 batch 操作：`batchGet()` 正确转换 keys 并返回 hydrated 对象
    - _Requirements: 10.1, 10.4_
  - [ ] 7.3 编写 Console Commands 和 ConsoleHelper 单元测试
    - 在 `ut/Unit/Console/Commands/` 下创建 `CreateSchemaCommandTest.php`、`UpdateSchemaCommandTest.php`、`DropSchemaCommandTest.php`
    - 在 `ut/Unit/Console/ConsoleHelperTest.php` 创建测试
    - 测试命令配置：name、description、options
    - 测试 `getManagedItemClasses()` 的类过滤逻辑（跳过 `NotAnnotatedException`、`ReflectionException`）
    - 测试 `ConsoleHelper::addCommands()` 注册三个命令
    - _Requirements: 10.7, 10.8_
  - [ ] 7.4 编写异常类继承关系测试
    - 在 `ut/Unit/Exceptions/ExceptionHierarchyTest.php` 创建测试
    - 验证 `ODMException extends RuntimeException`
    - 验证 `AnnotationParsingException extends ODMException`
    - 验证 `NotAnnotatedException extends ODMException`
    - 验证 `UnderlyingDatabaseException extends ODMException`
    - 验证 `DataConsistencyException extends RuntimeException`（非 ODMException）
    - _Requirements: 10.9_
  - [ ] 7.5 补充无效参数异常测试
    - 在各测试文件中补充异常路径测试
    - 测试 `ItemReflection::dehydrate()` 传入非对象时抛出 `ODMException`
    - 测试 `ItemReflection::hydrate()` 传入错误类型对象时抛出 `ODMException`
    - 测试 `ItemRepository::persist()` 传入错误类型对象时抛出 `ODMException`
    - 测试 `ItemRepository::get()` 传入未定义字段时抛出 `ODMException`
    - _Requirements: 10.10_
  - [ ] 7.6 运行全部单元测试验证通过
    - 执行 `vendor/bin/phpunit --testsuite unit`
    - 确认所有单元测试通过
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10_
  - [ ] 7.7 Checkpoint: 验证全部单元测试通过，commit

- [ ] 8. Property-Based Testing（PBT）
  - [ ] 8.1 编写 Property 1: Hydrate/Dehydrate Round-Trip 测试
    - 在 `ut/PBT/HydrateDehydrateRoundTripTest.php` 创建测试
    - 使用 Eris 生成覆盖 DynamoDB 全部数据类型（S/N/B/BOOL/NULL/L/M/SS/NS/BS）的 field data 数组
    - 验证 `dehydrate(hydrate(data))` 产生与原始数据等价的数组（考虑 null/空字符串等价性）
    - 注释引用：`// Feature: release-2.0, Property 1: Hydrate/Dehydrate Round-Trip`
    - **Property 1: Hydrate/Dehydrate Round-Trip**
    - **Validates: Requirements 11.2**
  - [ ] 8.2 编写 Property 2: Dirty Data Detection Idempotence 测试
    - 在 `ut/PBT/DirtyDataIdempotenceTest.php` 创建测试
    - 复用 Property 1 的数据生成器
    - 验证未修改对象的 `hasDirtyData()` 返回 false
    - 注释引用：`// Feature: release-2.0, Property 2: Dirty Data Detection Idempotence`
    - **Property 2: Dirty Data Detection Idempotence**
    - **Validates: Requirements 11.3**
  - [ ] 8.3 编写 Property 3: CAS Check Condition Invariant 测试
    - 在 `ut/PBT/CasCheckConditionTest.php` 创建测试
    - 生成包含 CAS 字段的原始数据
    - 验证 `getCheckConditionData()` 返回原始 CAS 字段值
    - 注释引用：`// Feature: release-2.0, Property 3: CAS Check Condition Invariant`
    - **Property 3: CAS Check Condition Invariant**
    - **Validates: Requirements 11.4**
  - [ ] 8.4 编写 Property 4: PartitionedHashKey Computation Determinism 测试
    - 在 `ut/PBT/PartitionedHashKeyTest.php` 创建测试
    - 生成随机字符串作为 base value 和 hash source
    - 验证两次计算产生相同结果
    - 注释引用：`// Feature: release-2.0, Property 4: PartitionedHashKey Computation Determinism`
    - **Property 4: PartitionedHashKey Computation Determinism**
    - **Validates: Requirements 11.5**
  - [ ] 8.5 编写 Property 5: Index Keys Consistency 测试
    - 在 `ut/PBT/IndexKeysConsistencyTest.php` 创建测试
    - 生成随机 hash/range 字符串
    - 验证 `getKeys()` 返回正确数量的元素（1 或 2）
    - 注释引用：`// Feature: release-2.0, Property 5: Index Keys Consistency`
    - **Property 5: Index Keys Consistency**
    - **Validates: Requirements 11.6**
  - [ ] 8.6 编写 Property 6: Partitioned Values Output Size 测试
    - 在 `ut/PBT/PartitionedValuesTest.php` 创建测试
    - 生成随机正整数（partition size）和字符串（base value）
    - 验证 `getAllPartitionedValues()` 返回恰好 `size` 个元素
    - 注释引用：`// Feature: release-2.0, Property 6: Partitioned Values Output Size`
    - **Property 6: Partitioned Values Output Size**
    - **Validates: Requirements 11.7**
  - [ ] 8.7 运行全部 PBT 测试验证通过
    - 执行 `vendor/bin/phpunit --testsuite pbt`
    - 确认所有 PBT 测试通过
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7_
  - [ ] 8.8 Checkpoint: 验证全部 PBT 测试通过，检查 unit+pbt 覆盖率 ≥85%，commit

- [ ] 9. 集成测试补齐
  - [ ] 9.1 编写 CRUD 生命周期集成测试
    - 在 `ut/Integration/` 下补齐集成测试（可在现有 `ItemManagerTest.php` 中扩展或新建文件）
    - 覆盖完整 CRUD 生命周期：create（persist + flush）→ read（get）→ update（modify + flush）→ delete（remove + flush）
    - _Requirements: 13.1_
  - [ ] 9.2 编写 Query、Scan、BatchGet 集成测试
    - 测试 Query 操作：使用 GSI 和 filter expressions
    - 测试 Scan 操作：包括 parallel scan
    - 测试 BatchGet 操作
    - _Requirements: 13.2, 13.3, 13.4_
  - [ ] 9.3 编写 CAS 相关集成测试
    - 测试 CAS 冲突检测：两个 ItemManager 并发修改同一 item，flush 抛出 `DataConsistencyException`
    - 测试 CAS 时间戳自动更新行为
    - _Requirements: 13.5, 13.6_
  - [ ] 9.4 编写 PartitionedHashKey 和 Projected Item 集成测试
    - 测试 PartitionedHashKey 的 multi-query 操作
    - 测试 projected Item 只读强制（修改后 flush 抛出 `ODMException`）
    - _Requirements: 13.7, 13.8_
  - [ ] 9.5 编写 Schema 工具集成测试
    - 测试 `DynamoDbSchemaTool` 的 create、update、drop 操作
    - _Requirements: 13.9_
  - [ ] 9.6 运行全部集成测试验证通过
    - 执行 `vendor/bin/phpunit --testsuite integration`
    - 确认所有集成测试通过（需有效 DynamoDB 环境）
    - 检查集成测试覆盖率 ≥75%
    - _Requirements: 8.4, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 12.2_
  - [ ] 9.7 Checkpoint: 验证全部集成测试通过，commit

- [ ] 10. 文档更新
  - [ ] 10.1 更新 `PROJECT.md`
    - PHP ≥8.5、PHPUnit ^13.0
    - `symfony/console` ^7.0、`symfony/finder` ^7.0
    - 移除 `doctrine/annotations` 和 `doctrine/common` 的记录
    - 新增 `giorgiosironi/eris` ^1.0（dev）
    - 更新测试命令：`--testsuite unit`、`--testsuite integration`、`--testsuite pbt`
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6_
  - [ ] 10.2 更新 `docs/state/architecture.md`
    - 注解体系 section：从 docblock 注解更新为 PHP 8 原生 Attribute，命名空间从 `Annotations` 改为 `Attributes`
    - 核心类职责 — `ItemManager`：构造函数描述从 4 参数改为 2 参数
    - 核心类职责 — `ItemReflection`：解析方式从 `Reader` 改为 `ReflectionAPI`
    - DBAL 抽象 section：`ItemManager` 构造时不再接受 `$cacheDir`/`$isDev`
    - _Requirements: 14.7, 14.8, 14.9_
  - [ ] 10.3 Checkpoint: 验证文档内容准确，commit

- [ ] 11. 手工测试
  - [ ] 11.1 Increment alpha tag
    - 查询已有 `v2.0.0-alpha.*` tag，取最大序号 +1，打新 tag（如 `v2.0.0-alpha.1`）
  - [ ] 11.2 验证 `composer install` 在 PHP 8.5 环境下成功
    - 确认无 deprecation warnings 和 abandoned package 警告
    - _Requirements: 1.3, 2.8_
  - [ ] 11.3 验证 `vendor/bin/phpunit --version` 报告 13.x
    - _Requirements: 1.4_
  - [ ] 11.4 验证三个 test suite 可独立运行
    - `vendor/bin/phpunit --testsuite unit`
    - `vendor/bin/phpunit --testsuite pbt`
    - `vendor/bin/phpunit --testsuite integration`（需 DynamoDB 环境）
    - _Requirements: 9.5, 9.6, 9.7_
  - [ ] 11.5 验证覆盖率目标
    - unit + pbt 行覆盖率 ≥85%
    - integration 行覆盖率 ≥75%
    - _Requirements: 12.1, 12.2, 12.3_
  - [ ] 11.6 Checkpoint: 手工测试全部通过，commit

- [ ] 12. Code Review
  - [ ] 12.1 委托给 code-reviewer sub-agent 对本 release 分支的全部变更执行 Code Review，发现问题直接修复
  - [ ] 12.2 Checkpoint: Code Review 完成，所有问题已修复，commit

## Issues

（stabilize 阶段新发现的 issue 记录于此，初始为空）

## Notes

- 执行时须遵循 `spec-execution.md` 中的规范
- **Commit 时机**：commit 随 checkpoint 一起执行，每个 top-level task 的最后一个 sub-task（Checkpoint）包含 commit 动作
- **TDD 编排**：task 2-4 采用 RED → GREEN 模式，先写测试（预期失败），再写实现（测试通过）。对于基础设施类 task（task 1：依赖升级、phpunit.xml）不需要 TDD，直接实现
- **目录重命名**：task 2.2 使用 `git mv src/Annotations src/Attributes` 保留 git 历史，在同一 task 内完成所有引用更新
- **`match(true)` 替换**：task 5.3 中 `ManagedItemState::isDataEqual()` 的 `switch(true)` 替换为 `match(true)`（Design CR Q4 决策）
- **集成测试环境**：task 9 和手工测试 task 11.4 需要有效的 AWS DynamoDB 环境和凭证
- **覆盖率驱动**：使用 Xdebug 或 PCOV 作为覆盖率驱动
- **Release 分支**：当前在 `release/2.0` 分支上工作

## Socratic Review

### Q1: 是否覆盖了 requirements 中的全部 14 条 Requirement？

**A:** 是。逐条对应：
- Requirement 1（PHP/PHPUnit 版本）→ Task 1.1, 11.2, 11.3
- Requirement 2（Composer 依赖）→ Task 1.1
- Requirement 3（Attribute 迁移）→ Task 2.1-2.8
- Requirement 4（ItemReflection 解析迁移）→ Task 3.1-3.4
- Requirement 5（ItemManager 简化）→ Task 4.1-4.4
- Requirement 6（PHP 8.x 语法升级）→ Task 5.1-5.10
- Requirement 7（测试模型迁移）→ Task 6.1
- Requirement 8（PHPUnit 13 适配）→ Task 6.2
- Requirement 9（phpunit.xml 配置）→ Task 1.2, 11.4
- Requirement 10（单元测试）→ Task 2.1, 3.1, 4.1, 7.1-7.6
- Requirement 11（PBT）→ Task 8.1-8.7
- Requirement 12（覆盖率）→ Task 8.8, 9.6, 11.5
- Requirement 13（集成测试）→ Task 6.2, 9.1-9.7
- Requirement 14（文档更新）→ Task 10.1-10.3

### Q2: Design CR 的四个决策是否在 tasks 中体现？

**A:** 是。
- Q1（按变更类型拆分）→ Task 1-10 的整体编排维度
- Q2（TDD 编排）→ Task 2（RED: 2.1 → GREEN: 2.7）、Task 3（RED: 3.1 → GREEN: 3.3）、Task 4（RED: 4.1 → GREEN: 4.3）
- Q3（`git mv` 保留历史）→ Task 2.2 明确使用 `git mv`
- Q4（`match(true)` 替换）→ Task 5.3 明确替换 `switch(true)` 为 `match(true)`

### Q3: Requirements CR 的四个决策是否在 tasks 中体现？

**A:** 是。
- Q1（抽象类同步升级）→ Task 5.5（AbstractDbConnection）和 5.7（AbstractSchemaTool）明确 `protected` 方法签名与子类同步收紧
- Q2（覆盖 DynamoDB 全部数据类型）→ Task 8.1 明确生成覆盖 S/N/B/BOOL/NULL/L/M/SS/NS/BS 的数据
- Q3（`ut/` 下新增子目录不违反 Non-Goals）→ Task 1.2 创建 `ut/Unit/`、`ut/Integration/`、`ut/PBT/`
- Q4（mock Connection 间接测试 SchemaTool）→ Task 7.3 通过 mock 测试 Console Commands 和 ConsoleHelper

### Q4: TDD 编排是否正确（RED → GREEN）？

**A:** 是。Task 2-4 严格遵循 TDD：
- Task 2：2.1 写测试（RED）→ 2.2-2.6 实现 → 2.7 验证通过（GREEN）
- Task 3：3.1 写测试（RED）→ 3.2 实现 → 3.3 验证通过（GREEN）
- Task 4：4.1 写测试（RED）→ 4.2 实现 → 4.3 验证通过（GREEN）
- Task 1（基础设施）不需要 TDD，直接实现
- Task 5（语法升级）是对已有代码的重构，不需要 TDD（已有测试覆盖）
- Task 7-9（补齐测试）本身就是写测试，不适用 TDD

### Q5: Checkpoint 编排是否符合规范？

**A:** 是。每个 top-level task 的最后一个 sub-task 都是 Checkpoint，包含验证步骤和 commit 动作。无独立的 top-level Checkpoint task。

### Q6: 是否存在孤立代码或未集成的步骤？

**A:** 不存在。每个 task 都建立在前一个 task 的基础上：
- Task 1 搭建基础设施 → Task 2 迁移 Attribute 类 → Task 3 重写解析层（依赖 Task 2 的新 Attribute）→ Task 4 简化构造函数（依赖 Task 3 的无参 parse()）→ Task 5 语法升级 → Task 6 测试模型迁移（依赖 Task 2-4 的新 API）→ Task 7-9 测试 → Task 10 文档

### Q7: top-level task 结构是否符合规范（1~N 实现 → N+1 手工测试 → 最后 Code Review）？

**A:** 是。Task 1-10 为实现 task，Task 11 为手工测试，Task 12 为 Code Review。

### Q8: Release 分支额外规则是否满足？

**A:** 是。手工测试 task（Task 11）的第一个 sub-task 为 "11.1 Increment alpha tag"。

### Q9: 是否存在禁止的 task 类型？

**A:** 不存在。所有 task 都是编写、修改或测试代码的任务。无用户验收测试、部署、性能分析等禁止类型。

### Q10: 所有 tasks 是否都是 mandatory（无 optional）？

**A:** 是。所有 sub-task 均使用 `- [ ]` 格式，无 `- [ ]*` optional 标记。

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [内容] Requirement 3 AC 11（ItemReflection 使用 PHP reflection API 正确解析 Attributes）未被任何 task 引用，已在 Task 3.1 和 3.2 的 `_Requirements:` 中补充 `3.11`
- [内容] Requirement 6 AC 4（`readonly` modifier）未被任何 task 引用，已在 Task 2.4（Item 类使用 `readonly`）的 `_Requirements:` 中补充 `6.4`
- [结构] Code Review task（Task 12.1）展开了详细的 review checklist 和 fix policy，违反 steering 规范（应委托 code-reviewer sub-agent，不展开 checklist）。已简化为"委托给 code-reviewer sub-agent 执行"
- [结构] Notes section 缺少 commit 时机说明，已补充"commit 随 checkpoint 一起执行"
- [结构] Release spec 缺少 `## Issues` section（stabilize 阶段 issue 记录），已在 `## Notes` 前新增

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 中的模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] Release spec 手工测试 top-level task 的第一个 sub-task 是 "Increment alpha tag"（Task 11.1）
- [x] 最后一个 top-level task 是 Code Review（Task 12）
- [x] 倒数第二个 top-level task 是手工测试（Task 11）
- [x] 自动化实现 task（Task 1-10）排在手工测试和 Code Review 之前
- [x] top-level task 有序号（1-12），连续无跳号
- [x] sub-task 有层级序号（N.M），连续无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款
- [x] requirements.md 中的每条 requirement 至少被一个 task 引用（14 条全覆盖）
- [x] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在（无悬空引用）
- [x] top-level task 按依赖关系排序（无循环依赖）
- [○] Graphify 跨模块依赖校验：跳过（`graphify_ready = false`，GRAPH_REPORT.md 不存在）
- [x] Checkpoint 不作为独立 top-level task，而是每个 top-level task 的最后一个 sub-task
- [x] 每个 top-level task 的最后一个 sub-task 是 Checkpoint
- [x] Checkpoint 描述中包含具体的验证命令和 commit 动作
- [x] TDD 编排正确：Task 2-4 遵循 RED → GREEN（先写测试再写实现）
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory（无 optional）
- [x] 手工测试 top-level task 存在（Task 11）
- [x] 手工测试覆盖了 requirements 中的关键用户场景
- [x] Code Review 是最后一个 top-level task（Task 12）
- [x] Code Review 描述为委托给 code-reviewer sub-agent 执行
- [x] `## Notes` section 存在
- [x] Notes 明确提到执行时须遵循 `spec-execution.md`
- [x] Notes 明确说明 commit 随 checkpoint 一起执行
- [x] Notes 包含当前 spec 特有的执行要点（TDD 编排、目录重命名、match(true)、集成测试环境、覆盖率驱动、Release 分支）
- [x] `## Issues` section 存在（Release spec 要求）
- [x] Socratic Review 存在且覆盖度充分（Q1-Q10）
- [x] Design CR 四个决策（Q1-Q4）均在 tasks 中体现
- [x] Requirements CR 四个决策（Q1-Q4）均在 tasks 中体现
- [x] Design 全覆盖：tasks 覆盖了 design 中的所有模块、接口和实现项
- [x] 可独立执行：每个 sub-task 描述自包含
- [x] 验收闭环：Checkpoint + 手工测试 + Code Review 构成完整验收
- [x] 执行路径无歧义：task 排序和依赖关系清晰
