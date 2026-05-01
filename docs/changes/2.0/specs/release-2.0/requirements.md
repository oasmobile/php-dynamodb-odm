# Requirements Document

oasis/dynamodb-odm Release 2.0 现代化升级的需求规格。

---

## Introduction

本文档定义 oasis/dynamodb-odm 2.0 版本的全面现代化升级需求。升级范围包括：PHP 最低版本提升至 8.5、PHPUnit 升级至 13、移除已废弃的 `doctrine/annotations` 和 `doctrine/common` 依赖并迁移到 PHP 8 原生 Attributes、源代码语法全面升级到 PHP 8.x 风格、简化 `ItemManager` 构造函数、补齐隔离单元测试和 PBT、补齐集成测试、更新配置和文档。

升级在保持公共 API 方法签名语义不变的前提下进行，不引入新功能特性，不改变项目目录结构。

**不涉及的内容**：不改变公共 API 的方法签名语义（参数类型、返回类型可收紧）；不引入新功能特性；不改变项目目录结构（`src/`、`ut/` 保持不变，仅在 `ut/` 下新增子目录）；不处理 `oasis/aws-wrappers` 和 `oasis/logging` 的内部升级（仅升级版本约束）；不引入 CI/CD 配置；`Connection` 接口声明的 `__construct` 不做改变。

---

## Glossary

- **ODM**: Object Data Mapping，对象数据映射
- **Attribute**: PHP 8 原生 Attribute（`#[...]` 语法），用于替代 doctrine/annotations 的 docblock 注解
- **Annotation**: doctrine/annotations 提供的 docblock 注解（`@Item`、`@Field` 等），本次升级的迁移源
- **ItemManager**: 核心管理器类，统一入口，管理对象生命周期
- **ItemReflection**: 元数据解析层，将 Attribute 解析为运行时反射信息
- **ItemRepository**: 仓库层，负责 CRUD / Query / Scan 操作
- **ManagedItemState**: 状态追踪层，负责脏检查、CAS、PartitionedHashKey 更新
- **Connection**: 数据库连接接口，定义所有底层数据操作方法签名
- **DynamoDbConnection**: `Connection` 的 DynamoDB 实现
- **SchemaTool**: Schema 管理工具，负责 DynamoDB 表的创建 / 更新 / 删除
- **CAS**: Check-and-Set，乐观并发控制机制
- **PartitionedHashKey**: 分区哈希键，用于将数据分散到多个分区
- **PBT**: Property-Based Testing，属性测试
- **Eris**: PHP 的 PBT 库（`giorgiosironi/eris`）
- **hydrate**: 将 DynamoDB 数组数据填充到 PHP 对象
- **dehydrate**: 将 PHP 对象提取为 DynamoDB 数组数据
- **Constructor_Promotion**: PHP 8.0 构造函数参数提升语法
- **Typed_Property**: PHP 7.4+ 类属性类型声明
- **Round_Trip**: 往返测试，对序列化/反序列化操作验证 `parse(print(x)) == x`
- **DataConsistencyException**: CAS 冲突时抛出的异常
- **NotAnnotatedException**: 类未标注 `#[Item]` Attribute 时抛出的异常
- **GSI**: Global Secondary Index，DynamoDB 全局二级索引
- **Composer**: PHP 依赖管理工具
- **PHPUnit**: PHP 单元测试框架

---

## Requirements

### Requirement 1: PHP 与 PHPUnit 版本升级

**User Story:** 作为开发者，我希望项目要求 PHP 8.5 和 PHPUnit 13，以便使用现代语言特性和最新的测试框架。

#### Acceptance Criteria

1. THE `composer.json` SHALL declare `php` requirement as `>=8.5`
2. THE `composer.json` SHALL declare `phpunit/phpunit` requirement as `^13.0`
3. WHEN `composer install` is executed in a PHP 8.5 environment, THE Composer SHALL resolve all dependencies without conflict
4. WHEN `vendor/bin/phpunit --version` is executed, THE PHPUnit SHALL report a version in the 13.x series

---

### Requirement 2: Composer 依赖升级

**User Story:** 作为开发者，我希望所有 Composer 依赖升级到兼容 PHP 8.5 的最新稳定版，以便项目不再依赖已停止维护的包。

#### Acceptance Criteria

1. THE `composer.json` SHALL declare `symfony/console` requirement as `^7.0`
2. THE `composer.json` SHALL declare `symfony/finder` requirement as `^7.0`
3. THE `composer.json` SHALL declare `oasis/aws-wrappers` requirement as the latest major version compatible with PHP 8.5
4. THE `composer.json` SHALL declare `oasis/logging` requirement as the latest major version compatible with PHP 8.5
5. THE `composer.json` SHALL NOT contain `doctrine/annotations` in `require` or `require-dev` sections
6. THE `composer.json` SHALL NOT contain `doctrine/common` in `require` or `require-dev` sections
7. THE `composer.json` SHALL declare `giorgiosironi/eris` requirement as `^1.0` in the `require-dev` section
8. WHEN `composer install` is executed, THE Composer SHALL complete without deprecation warnings related to abandoned packages

---

### Requirement 3: Annotation 到 Attribute 迁移

**User Story:** 作为开发者，我希望注解系统从 doctrine/annotations docblock 注解迁移到 PHP 8 原生 Attribute，以便项目不再依赖已废弃的 doctrine/annotations 包。

#### Acceptance Criteria

1. THE `Field` class SHALL be declared as a PHP 8 Attribute with `#[Attribute(Attribute::TARGET_PROPERTY)]`
2. THE `Item` class SHALL be declared as a PHP 8 Attribute with `#[Attribute(Attribute::TARGET_CLASS)]`
3. THE `Index` class SHALL be usable as a nested Attribute within `Item`
4. THE `PartitionedHashKey` class SHALL be declared as a PHP 8 Attribute with `#[Attribute(Attribute::TARGET_PROPERTY)]`
5. THE `Field` class SHALL preserve all existing public properties: `name`, `type`, `cas`, `gsiKey`
6. THE `Item` class SHALL preserve all existing public properties: `table`, `primaryIndex`, `globalSecondaryIndices`, `localSecondaryIndices`, `repository`, `projected`
7. THE `PartitionedHashKey` class SHALL preserve all existing public properties: `size`, `hashField`, `baseField`
8. THE `Index` class SHALL preserve all existing public properties: `hash`, `range`, `name`
9. THE Attribute classes SHALL NOT import or reference any `Doctrine\Common\Annotations` namespace
10. THE Attribute classes SHALL reside in the `src/Attributes/` directory under the namespace `Oasis\Mlib\ODM\Dynamodb\Attributes`, replacing the former `src/Annotations/` directory and `Oasis\Mlib\ODM\Dynamodb\Annotations` namespace
11. WHEN an Item class uses `#[Item(...)]`, `#[Field(...)]`, `#[Index(...)]`, `#[PartitionedHashKey(...)]` Attributes, THE ItemReflection SHALL parse the Attributes correctly using PHP reflection API

---

### Requirement 4: ItemReflection 解析迁移

**User Story:** 作为开发者，我希望 ItemReflection 的解析方法使用 PHP 反射 API 读取原生 Attribute 而非 Doctrine Reader，以便元数据解析层与 doctrine/annotations 解耦。

#### Acceptance Criteria

1. THE `ItemReflection::parse()` method SHALL NOT accept a `Reader` parameter
2. THE `ItemReflection::parse()` method SHALL use `ReflectionClass::getAttributes()` and `ReflectionProperty::getAttributes()` to read Attribute metadata
3. WHEN a class is annotated with `#[Item(...)]`, THE `ItemReflection::parse()` SHALL extract the `Item` Attribute and populate `itemDefinition`
4. WHEN a property is annotated with `#[Field(...)]`, THE `ItemReflection::parse()` SHALL extract the `Field` Attribute and populate `fieldDefinitions`, `propertyMapping`, `attributeTypes`
5. WHEN a property is annotated with `#[PartitionedHashKey(...)]`, THE `ItemReflection::parse()` SHALL extract the `PartitionedHashKey` Attribute and populate `partitionedHashKeys`
6. WHEN a property has `Field::cas` set to a value other than `disabled`, THE `ItemReflection::parse()` SHALL populate `casProperties` with the property name and CAS type
7. IF a class lacks the `#[Item]` Attribute, THEN THE `ItemReflection::parse()` SHALL throw `NotAnnotatedException`
8. THE `ItemReflection` class SHALL NOT import or reference any `Doctrine\Common\Annotations` namespace

---

### Requirement 5: ItemManager 构造函数简化

**User Story:** 作为开发者，我希望 ItemManager 构造函数移除 `$cacheDir` 和 `$isDev` 参数，以便构造 API 更简洁且不再依赖 doctrine 注解缓存。

#### Acceptance Criteria

1. THE `ItemManager::__construct()` SHALL accept only two parameters: `$dbCnn` (array or Connection) and `$defaultTablePrefix` (string)
2. THE `ItemManager` SHALL NOT use `AnnotationRegistry`, `CachedReader`, `AnnotationReader`, or `FilesystemCache`
3. THE `ItemManager` SHALL NOT import or reference any `Doctrine\Common\Annotations` or `Doctrine\Common\Cache` namespace
4. THE `ItemManager::getItemReflection()` SHALL call `ItemReflection::parse()` without passing a Reader argument
5. WHEN `ItemManager` is constructed with a `Connection` instance, THE `ItemManager` SHALL store the Connection for later use
6. WHEN `ItemManager` is constructed with an array config, THE `ItemManager` SHALL store the config and create `DynamoDbConnection` on demand

---

### Requirement 6: PHP 8.x 语法全面升级

**User Story:** 作为开发者，我希望 `src/` 下所有源代码升级到 PHP 8.x 语法风格，以便代码库现代、简洁并充分利用最新语言特性。

#### Acceptance Criteria

1. THE source code SHALL use Typed_Property declarations for all class properties where the type is deterministic
2. THE source code SHALL use Constructor_Promotion where it simplifies constructor definitions
3. THE source code SHALL use `match` expressions to replace simple `switch` statements where applicable
4. THE source code SHALL use `readonly` modifier for properties that are set once and never modified
5. THE source code SHALL use `str_contains()` and `str_starts_with()` to replace `strpos()` patterns where applicable
6. THE source code SHALL NOT call `ReflectionProperty::setAccessible(true)` because PHP 8.1+ reflection is accessible by default
7. THE source code SHALL use union types, nullable types, and return type declarations where the type is deterministic
8. THE source code SHALL NOT change the semantic behavior of any public API method (parameter semantics, return value semantics)

---

### Requirement 7: 测试模型 Attribute 迁移

**User Story:** 作为开发者，我希望 `ut/` 下所有测试模型类从 docblock 注解迁移到 PHP 8 原生 Attribute，以便测试模型与新的 Attribute 系统保持一致。

#### Acceptance Criteria

1. THE `User` class SHALL use `#[Item(...)]`, `#[Field(...)]`, `#[Index(...)]`, `#[PartitionedHashKey(...)]` Attributes instead of docblock annotations
2. THE `Game` class SHALL use `#[Item(...)]`, `#[Field(...)]`, `#[Index(...)]`, `#[PartitionedHashKey(...)]` Attributes instead of docblock annotations
3. THE `ConsoleGame` class SHALL use native Attributes instead of docblock annotations
4. THE `BasicGameInfo` class SHALL use native Attributes instead of docblock annotations
5. THE `CasDemoUser` class SHALL use native Attributes instead of docblock annotations
6. THE test model classes SHALL NOT contain any `@Item`, `@Field`, `@Index`, or `@PartitionedHashKey` docblock annotations
7. THE test model classes SHALL NOT import any `Doctrine\Common\Annotations` namespace
8. THE test model classes SHALL import from `Oasis\Mlib\ODM\Dynamodb\Attributes` namespace instead of `Oasis\Mlib\ODM\Dynamodb\Annotations`

---

### Requirement 8: 现有测试适配 PHPUnit 13

**User Story:** 作为开发者，我希望现有测试文件 `ut/ItemManagerTest.php` 适配 PHPUnit 13 API，以便现有集成测试在新测试框架下继续工作。

#### Acceptance Criteria

1. THE `ItemManagerTest` SHALL extend `PHPUnit\Framework\TestCase` instead of `PHPUnit_Framework_TestCase`
2. THE `setUp()` method SHALL declare `: void` return type
3. THE `ItemManagerTest::setUp()` SHALL construct `ItemManager` with only two parameters (`$dbCnn` and `$defaultTablePrefix`), matching the simplified constructor
4. WHEN the existing integration tests are executed, THE PHPUnit SHALL report all tests as passing (assuming a valid DynamoDB environment)

---

### Requirement 9: PHPUnit 配置与 Test Suite 分离

**User Story:** 作为开发者，我希望 `phpunit.xml` 更新为 PHPUnit 13 schema 并分离三个 test suite（unit、integration、pbt），以便可以独立运行不同类别的测试。

#### Acceptance Criteria

1. THE `phpunit.xml` SHALL use the PHPUnit 13 compatible XML schema
2. THE `phpunit.xml` SHALL define a `unit` test suite pointing to `ut/Unit/` directory
3. THE `phpunit.xml` SHALL define an `integration` test suite pointing to `ut/Integration/` directory
4. THE `phpunit.xml` SHALL define a `pbt` test suite pointing to `ut/PBT/` directory
5. WHEN `vendor/bin/phpunit --testsuite unit` is executed, THE PHPUnit SHALL run only unit tests
6. WHEN `vendor/bin/phpunit --testsuite integration` is executed, THE PHPUnit SHALL run only integration tests
7. WHEN `vendor/bin/phpunit --testsuite pbt` is executed, THE PHPUnit SHALL run only PBT tests
8. THE `phpunit.xml` SHALL configure code coverage source to include `src/` directory

---

### Requirement 10: 隔离单元测试

**User Story:** 作为开发者，我希望通过 mock Connection 的隔离单元测试覆盖核心类，以便无需真实 DynamoDB 环境即可验证业务逻辑。

#### Acceptance Criteria

1. THE unit tests SHALL mock the `Connection` interface to isolate tests from DynamoDB
2. THE unit tests SHALL cover `ItemReflection` including: Attribute parsing, hydrate, dehydrate, property access, primary key extraction, field name mapping, partitioned hash key value generation
3. THE unit tests SHALL cover `ManagedItemState` including: dirty data detection, CAS timestamp update, partitioned hash key update, check condition data generation, state transitions
4. THE unit tests SHALL cover `ItemRepository` including: persist, get, remove, flush, detach, refresh, query field resolution, batch operations
5. THE unit tests SHALL cover `ItemManager` including: construction with Connection, construction with array config, namespace scanning, repository caching, persist/get/remove/flush delegation
6. THE unit tests SHALL cover all Attribute classes (`Field`, `Item`, `Index`, `PartitionedHashKey`) including: construction with valid parameters, default values, property access
7. THE unit tests SHALL cover Console Commands (`CreateSchemaCommand`, `UpdateSchemaCommand`, `DropSchemaCommand`) including: command configuration, managed item class resolution
8. THE unit tests SHALL cover `ConsoleHelper` including: command registration
9. THE unit tests SHALL cover exception classes to verify inheritance hierarchy
10. IF an invalid argument is passed to a core method, THEN THE unit test SHALL verify that the appropriate exception is thrown

---

### Requirement 11: Property-Based Testing

**User Story:** 作为开发者，我希望使用 Eris 进行属性测试以验证核心数据路径的不变量，以便系统性地发现数据转换和状态管理中的边界情况。

#### Acceptance Criteria

1. THE PBT tests SHALL use `giorgiosironi/eris` ^1.0 as the PBT framework
2. THE PBT tests SHALL verify the hydrate/dehydrate Round_Trip property: FOR ALL valid field data arrays, `dehydrate(hydrate(data))` SHALL produce an equivalent array (accounting for DynamoDB null/empty-string equivalence)
3. THE PBT tests SHALL verify dirty data detection idempotence: FOR ALL valid field data, a `ManagedItemState` initialized with data and not modified SHALL report `hasDirtyData()` as false
4. THE PBT tests SHALL verify CAS check condition generation: FOR ALL valid original data with CAS-enabled fields, `getCheckConditionData()` SHALL return the original values for CAS fields
5. THE PBT tests SHALL verify PartitionedHashKey computation determinism: FOR ALL valid base values and hash sources, computing the partitioned hash key twice with the same inputs SHALL produce the same result
6. THE PBT tests SHALL verify `Index::getKeys()` consistency: FOR ALL valid hash and range combinations, `getKeys()` SHALL return an array containing exactly the hash key and optionally the range key
7. THE PBT tests SHALL verify `getAllPartitionedValues()` output size: FOR ALL valid partition sizes and base values, `getAllPartitionedValues()` SHALL return exactly `size` elements

---

### Requirement 12: 测试覆盖率目标

**User Story:** 作为开发者，我希望单元测试加 PBT 达到至少 85% 行覆盖率、集成测试达到至少 75% 行覆盖率，以便代码库具有充分的测试覆盖。

#### Acceptance Criteria

1. WHEN unit tests and PBT tests are executed together with coverage enabled, THE coverage report SHALL show line coverage of `src/` at 85% or above
2. WHEN integration tests are executed with coverage enabled, THE coverage report SHALL show line coverage of `src/` at 75% or above
3. THE coverage measurement SHALL use PHPUnit 13 built-in coverage reporting with Xdebug or PCOV as the driver

---

### Requirement 13: 集成测试补齐

**User Story:** 作为开发者，我希望补齐覆盖完整 CRUD 生命周期和高级特性的集成测试（基于真实 DynamoDB 环境），以便验证端到端行为。

#### Acceptance Criteria

1. THE integration tests SHALL cover the complete CRUD lifecycle: create (persist + flush), read (get), update (modify + flush), delete (remove + flush)
2. THE integration tests SHALL cover Query operations with GSI and filter expressions
3. THE integration tests SHALL cover Scan operations including parallel scan
4. THE integration tests SHALL cover BatchGet operations
5. THE integration tests SHALL cover CAS conflict detection: WHEN two managers modify the same item concurrently, THE flush SHALL throw `DataConsistencyException`
6. THE integration tests SHALL cover CAS timestamp auto-update behavior
7. THE integration tests SHALL cover PartitionedHashKey-based multi-query operations
8. THE integration tests SHALL cover projected Item read-only enforcement
9. THE integration tests SHALL cover Schema tool operations (create, update, drop) via `DynamoDbSchemaTool`
10. THE integration tests SHALL be placed in `ut/Integration/` directory
11. THE existing `ut/ItemManagerTest.php` SHALL be relocated to `ut/Integration/` directory

---

### Requirement 14: 文档更新

**User Story:** 作为开发者，我希望 `PROJECT.md` 和 `docs/state/architecture.md` 更新以反映新版本信息，以便文档准确描述当前系统状态。

#### Acceptance Criteria

1. THE `PROJECT.md` SHALL reflect PHP ≥ 8.5 as the language requirement
2. THE `PROJECT.md` SHALL reflect PHPUnit ^13.0 as the test framework
3. THE `PROJECT.md` SHALL reflect `symfony/console` ^7.0 and `symfony/finder` ^7.0
4. THE `PROJECT.md` SHALL reflect the removal of `doctrine/annotations` and `doctrine/common` dependencies
5. THE `PROJECT.md` SHALL list `giorgiosironi/eris` ^1.0 as a dev dependency
6. THE `PROJECT.md` SHALL document the three test suite commands: `--testsuite unit`, `--testsuite integration`, `--testsuite pbt`
7. THE `docs/state/architecture.md` SHALL update the annotation system description from docblock annotations to PHP 8 native Attributes, including the namespace change from `Annotations` to `Attributes`
8. THE `docs/state/architecture.md` SHALL update `ItemManager` constructor description to reflect the simplified two-parameter signature
9. THE `docs/state/architecture.md` SHALL update `ItemReflection` description to reflect Attribute-based parsing instead of `Reader`-based parsing

---

## Socratic Review

### Q1: 是否覆盖了 goal.md 中所有 Clarification 决策？

**A:** 是。
- Q1（全面语法升级）→ Requirement 6 覆盖所有 PHP 8.x 语法特性
- Q2（迁移到原生 Attributes）→ Requirement 3、4 覆盖 Attribute 迁移和 `ItemReflection::parse()` 重写
- Q3（移除 `$cacheDir`/`$isDev`）→ Requirement 5 明确简化为两参数
- Q4（oasis 库直接升级）→ Requirement 2 AC 3-4 覆盖
- Q5（eris ^1.0）→ Requirement 2 AC 7 和 Requirement 11 AC 1 覆盖

### Q2: Non-Goals 是否在 requirements 中得到尊重？

**A:** 是。
- 不改变公共 API 方法签名语义 → Requirement 6 AC 8 明确约束
- 不引入新功能 → 所有 requirements 均为升级/迁移/测试，无新功能
- 不改变目录结构 → `src/`、`ut/` 保持不变，仅在 `ut/` 下新增子目录
- 不处理 oasis 库内部升级 → Requirement 2 仅升级版本约束
- 不引入 CI/CD → 无相关 requirement
- `Connection.__construct` 不变 → 未涉及 Connection 接口修改

### Q3: PBT 的属性选择是否合理？

**A:** 是。选择了 hydrate/dehydrate 往返（Round_Trip）、脏检查幂等性（Idempotence）、CAS 条件生成（Invariant）、PartitionedHashKey 计算确定性（Determinism）、Index 键一致性（Invariant）、分区值数量（Metamorphic）。这些属性覆盖了核心数据路径，且都是纯函数或内存操作，适合 PBT 的高频迭代。

### Q4: 是否存在遗漏的 EARS 模式违规？

**A:** 已检查。所有 AC 均遵循 EARS 模式（THE/WHEN/IF/WHILE/WHERE/FOR ALL）。系统名称均在 Glossary 中定义。无模糊术语、无代词、无逃逸子句。

### Q5: Attribute 迁移是否覆盖了所有四个注解类？

**A:** 是。Requirement 3 AC 1-4 分别覆盖 `Field`、`Item`、`Index`、`PartitionedHashKey`。Requirement 3 AC 5-8 确保属性保持不变。Requirement 3 AC 10 覆盖目录和命名空间从 `Annotations` 到 `Attributes` 的重命名。Requirement 7 覆盖所有测试模型类的迁移，AC 8 确保测试模型使用新命名空间。

### Q6: 测试覆盖率目标是否可达？

**A:** 合理。85% 单元测试 + PBT 覆盖率通过 mock Connection 可以覆盖 `src/` 中除 `DynamoDbConnection` 和 `DynamoDbSchemaTool`（依赖真实 AWS）之外的所有代码。75% 集成测试覆盖率通过完整 CRUD 生命周期和 Schema 操作可以达到。

### Q7: 各 requirement 之间是否存在矛盾或重叠？

**A:** 无矛盾。存在合理的依赖关系：Requirement 3（Attribute 迁移）和 Requirement 4（ItemReflection 解析迁移）有紧密关联但职责不同——前者定义 Attribute 类本身，后者定义解析层如何读取 Attribute。Requirement 5（ItemManager 简化）依赖 Requirement 4 的完成。Requirement 7（测试模型迁移）依赖 Requirement 3。Requirement 8（测试适配）依赖 Requirement 5。这些依赖关系是自然的，不构成矛盾。Requirement 2 AC 5-6（移除 doctrine 依赖）与 Requirement 3-5 的 doctrine 移除约束有重叠，但从不同角度（依赖声明 vs 代码引用）约束，属于互补而非冗余。

### Q8: 是否有遗漏的错误路径或边界条件？

**A:** 基本覆盖。Requirement 4 AC 7 覆盖了缺少 `#[Item]` 的异常路径。Requirement 10 AC 10 覆盖了无效参数的异常验证。Requirement 13 AC 5 覆盖了 CAS 冲突场景。潜在的未显式覆盖场景包括：Attribute 参数类型错误时的行为（如 `Field::type` 传入非法值）、`ItemManager` 以无效数组配置构造时的行为。但这些属于实现层面的防御性编程，在 design 阶段可以细化，requirements 层面的覆盖度已足够。

### Q9: 是否存在隐含的前置假设？

**A:** 存在以下隐含假设，均已在 goal.md 或 PRP-001 中有据可查：
- `oasis/aws-wrappers` 和 `oasis/logging` 的最新大版本支持 PHP 8.5（goal.md Q4 已确认）
- `giorgiosironi/eris` ^1.0 兼容 PHP 8.5 和 PHPUnit 13（goal.md Q5 已说明"大概率兼容"）
- 现有 `Connection` 接口的 `__construct` 声明在 PHP 8.5 下仍合法（PRP-001 Risks #4 已记录）
- 集成测试需要有效的 AWS DynamoDB 环境和凭证（Requirement 8 AC 4 和 Requirement 13 隐含）


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项

- [语体] 全部 14 条 User Story 从英文格式改为中文格式（`作为 <角色>，我希望 <能力>，以便 <价值>`）
- [结构] Introduction 补充了 Non-scope 段落，明确列出不涉及的内容（与 goal.md Non-Goals 对齐）
- [术语] Glossary 补充 5 个缺失术语：DataConsistencyException、NotAnnotatedException、GSI、Composer、PHPUnit
- [内容] Socratic Review 补充 Q7（requirement 间矛盾/重叠检查）、Q8（遗漏的错误路径/边界条件）、Q9（隐含前置假设）

### 合规检查

- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号连续 1-14，术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空，术语格式正确
- [x] Requirements section 存在且包含 14 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] Glossary 中的术语在正文 AC 中被实际使用（无孤立术语）
- [x] AC 中使用的领域概念在 Glossary 中有定义
- [x] 所有 User Story 使用中文格式
- [x] 所有 AC 遵循 EARS 模式（THE/WHEN/IF/FOR ALL）
- [x] AC 编号连续，无跳号
- [x] Goal CR 所有决策已体现在 requirements 中
- [x] Non-Goals 在 requirements 中得到尊重
- [x] Socratic Review 覆盖度充分
- [○] 内容边界（实现细节）：本 spec 为技术栈升级性质，AC 中引用具体类名、方法名和库名属于必要的领域术语引用，均已在 Glossary 中定义，判定为合理

### Clarification Round

**状态**: 已完成

**Q1:** Requirement 6（PHP 8.x 语法升级）要求对 `src/` 下所有文件全面升级，但未明确 `AbstractSchemaTool` 和 `AbstractDbConnection` 这类抽象基类的处理策略。抽象类中的 `protected` 方法签名收紧（如添加参数类型、返回类型）可能影响子类兼容性。design 阶段应如何处理？
- A) 抽象类与具体类同步升级，子类签名一并收紧（本项目子类均在 `src/` 内，可控）
- B) 抽象类仅升级 `public` 方法签名，`protected` 方法保持宽松以降低风险
- C) 其他（请说明）

**A:** A — 抽象类与具体类同步升级，子类签名一并收紧

**Q2:** Requirement 11（PBT）的 hydrate/dehydrate Round_Trip 测试需要生成"有效的 field data 数组"。DynamoDB 支持的数据类型包括 S/N/B/BOOL/NULL/L/M/SS/NS/BS 等，其中嵌套类型（L/M）和集合类型（SS/NS/BS）的生成策略会显著影响测试复杂度和发现能力。design 阶段应如何界定 PBT 数据生成的范围？
- A) 仅覆盖当前代码实际使用的类型子集（S/N/BOOL/NULL/L/M，参考现有 Field 类型定义）
- B) 覆盖 DynamoDB 全部数据类型，包括 SS/NS/BS 集合类型
- C) 分层覆盖：基础类型必覆盖，嵌套和集合类型作为扩展覆盖
- D) 其他（请说明）

**A:** B — 覆盖 DynamoDB 全部数据类型，包括 SS/NS/BS 集合类型

**Q3:** Requirement 13（集成测试）AC 11 要求将 `ut/ItemManagerTest.php` 迁移到 `ut/Integration/` 目录。但 goal.md Non-Goals 明确"不改变项目的目录结构（`src/`、`ut/` 保持不变）"。在 `ut/` 下新增 `Unit/`、`Integration/`、`PBT/` 子目录是否与 Non-Goals 冲突，取决于对"目录结构不变"的理解粒度。design 阶段应如何解读？
- A) Non-Goals 的"目录结构不变"指顶层目录不变（`src/`、`ut/` 不改名不移动），在 `ut/` 下新增子目录不算违反
- B) 严格解读，不在 `ut/` 下新增子目录，通过文件命名约定或 PHPUnit 配置的 `include`/`exclude` 模式区分 test suite
- C) 其他（请说明）

**A:** A — 顶层目录不变即可，`ut/` 下新增子目录不算违反

**Q4:** Requirement 10（隔离单元测试）要求覆盖 `DynamoDbSchemaTool`，但该类的核心方法（createTable、updateTable、dropTable）直接调用 DynamoDB API。mock 粒度的选择会影响测试的有效性和维护成本。design 阶段应如何处理？
- A) 通过 mock Connection 间接测试 SchemaTool 的逻辑（表定义生成、GSI 差异计算等），不 mock DynamoDB API 本身
- B) 将 SchemaTool 的 DynamoDB 调用部分排除出单元测试范围，仅在集成测试中覆盖
- C) 引入一层薄封装使 SchemaTool 可测试，但这可能与"不引入新功能"的 Non-Goal 冲突
- D) 其他（请说明）

**A:** A — 通过 mock Connection 间接测试 SchemaTool 的逻辑
