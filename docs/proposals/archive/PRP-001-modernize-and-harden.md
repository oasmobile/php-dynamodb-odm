# PRP-001 Modernize & Harden

本 proposal 描述对 oasis/dynamodb-odm 的一次全面现代化升级与质量加固。

---

## Status

`released`

---

## Background

当前项目基于 PHP 7.1 / PHPUnit 5.7 构建，依赖版本陈旧（`doctrine/annotations` ^1.4、`symfony/console` ^4.4 等）。测试仅有一个集成测试文件 `ut/ItemManagerTest.php`，依赖真实 AWS DynamoDB 环境，无法离线运行，且缺少对核心类的隔离单元测试。项目没有 PBT（Property-Based Testing），也没有覆盖率度量。

PHP 8.5 已发布，PHPUnit 13 是当前主流版本，继续停留在旧版本会导致：

- 无法使用 PHP 8.x 的语言特性（typed properties、match、enum、readonly 等）
- 依赖库不再维护旧版本的安全补丁
- 新开发者上手成本高

---

## Problem

1. **依赖过时**：PHP ≥7.1、PHPUnit ^5.7、doctrine/annotations ^1.4、symfony ^4.4 均已 EOL 或接近 EOL
2. **语法陈旧**：未使用 typed properties、constructor promotion、match expression、union types 等 PHP 8+ 特性
3. **测试不足**：
   - 仅有 1 个测试文件，所有测试依赖真实 DynamoDB（集成测试）
   - 无隔离单元测试（mock Connection）
   - 无 PBT
   - 无覆盖率度量
   - 核心类 `ItemReflection`、`ManagedItemState`、`ItemRepository` 的边界场景未覆盖
   - Console 命令、Schema 工具完全无测试
4. **集成测试缺乏结构**：现有测试虽然是集成测试，但未按集成测试标准组织（无独立 suite、无环境隔离标记）

---

## Goals

1. 将 PHP 最低版本升级到 8.5，PHPUnit 升级到 13
2. 升级所有 Composer 依赖到兼容 PHP 8.5 的最新稳定版
3. 将源代码语法升级到 PHP 8.x 风格（typed properties、constructor promotion、match、readonly 等，在不破坏公共 API 的前提下）
4. 补齐隔离单元测试（mock Connection），覆盖 `ItemReflection`、`ManagedItemState`、`ItemRepository`、`ItemManager`、Annotations、Console Commands、Schema Tools、Exceptions
5. 保证单元测试全部通过
6. 引入 PBT（使用 `giorgiosironi/eris` 1.x），对核心数据路径（hydrate/dehydrate 往返、脏检查、CAS 条件生成、PartitionedHashKey 计算）进行属性测试
7. 单元测试 + PBT 行覆盖率 ≥ 85%
8. 补齐集成测试（真实 AWS 环境），覆盖完整 CRUD 生命周期、Query/Scan、BatchGet、MultiQuery、CAS 冲突、Schema 工具、Projected Item 等场景，集成测试覆盖率 ≥ 75%
9. 更新 `phpunit.xml` 配置，分离 unit / integration / pbt 三个 test suite
10. 更新 `PROJECT.md` 和 `docs/state/architecture.md` 反映新版本信息

---

## Non-Goals

- 不改变公共 API 的方法签名（参数类型、返回类型可以收紧，但不改变语义）
- 不引入新的功能特性
- 不迁移注解系统到 PHP Attributes（这是一个独立的 proposal 范围）
- 不改变项目的目录结构（`src/`、`ut/` 保持不变）
- 不处理 `oasis/aws-wrappers` 和 `oasis/logging` 的内部升级（仅升级版本约束）
- 不引入 CI/CD 配置

---

## Scope

### 涉及的文件 / 目录

| 范围 | 说明 |
|------|------|
| `composer.json` | PHP 版本、依赖版本升级 |
| `phpunit.xml` | 升级到 PHPUnit 13 schema，分离 test suite |
| `src/**/*.php` | 语法升级（typed properties、constructor promotion 等） |
| `ut/**/*.php` | 现有测试适配 PHPUnit 13 API |
| `ut/` (新文件) | 新增单元测试、PBT、集成测试 |
| `PROJECT.md` | 更新技术栈版本信息 |
| `docs/state/architecture.md` | 更新版本相关描述 |

### 依赖升级目标

| 包 | 当前版本 | 目标版本 |
|----|----------|----------|
| `php` | ≥7.1 | ≥8.5 |
| `phpunit/phpunit` | ^5.7 | ^13.0 |
| `doctrine/annotations` | ^1.4 | ^2.0 |
| `doctrine/common` | ^2.7 | ^3.0 |
| `symfony/console` | ^4.4 | ^7.0 |
| `symfony/finder` | ^4.4 | ^7.0 |
| `oasis/aws-wrappers` | ^2.10 | 最新兼容版本 |
| `oasis/logging` | ^1.0 | 最新兼容版本 |
| `giorgiosironi/eris` | (新增) | ^1.0 |

### PHPUnit 13 迁移要点

- `PHPUnit_Framework_TestCase` → `PHPUnit\Framework\TestCase`
- `setUp()` / `tearDown()` 需要 `: void` 返回类型
- `expectException()` API 变化
- `phpunit.xml` schema 更新
- `@depends` / `@dataProvider` 注解保持兼容或迁移到 Attributes

### PHP 8.x 语法升级要点

- 类属性添加类型声明（typed properties）
- 适当使用 constructor promotion
- `match` 替代简单 `switch`
- `?->` null safe operator（适用场景）
- `readonly` 用于不可变属性
- `str_contains` / `str_starts_with` 替代 `strpos` 模式
- 移除不必要的 `setAccessible(true)`（PHP 8.1+ 反射默认可访问）

---

## References

- [PHPUnit 13 Migration Guide](https://docs.phpunit.de/en/13.0/migration.html)
- [PHP 8.x Migration Guides](https://www.php.net/manual/en/migration80.php)
- [Eris PBT Library](https://github.com/giorgiosironi/eris)
- `docs/state/architecture.md` — 当前架构 SSOT

---

## Risks

1. **`doctrine/common` ^3.0 移除了 `Doctrine\Common\Cache\FilesystemCache`**：`ItemManager` 构造函数直接使用 `CachedReader` + `FilesystemCache`。升级后需要替换为 `symfony/cache` 的 `FilesystemAdapter` 或 `doctrine/annotations` 2.0 内置的缓存机制。这是一个必须在 design 阶段解决的兼容性问题
2. **`AnnotationRegistry::registerLoader()` 在 `doctrine/annotations` 2.0 中已废弃**：需要移除该调用，改用 Composer autoloader 自动发现（2.0 默认行为）
3. **`oasis/aws-wrappers` 和 `oasis/logging` 的 PHP 8.5 兼容性未知**：如果这些包不支持 PHP 8.5，可能需要放宽版本约束或 fork
4. **`Connection` 接口声明了 `__construct`**：PHP 8.x 中接口声明构造函数虽然合法但不推荐，本次不改变以保持 API 兼容

---

## Notes

- 现有 `ut/ItemManagerTest.php` 中的测试全部依赖真实 DynamoDB，升级后应归入 integration suite
- `doctrine/annotations` ^2.0 仍然支持 docblock 注解，不需要迁移到 PHP Attributes
- PBT 重点覆盖数据转换路径（hydrate ↔ dehydrate 的往返一致性）和状态机（ManagedItemState 的状态转换合法性）
- 集成测试需要 AWS 凭证和 DynamoDB 访问权限，应通过 `ut/ut.yml` 配置
- 开发环境中 `php74` 命令对应 PHP 7.4（用于执行老版本验证），`php` 命令对应 PHP 8.5。升级前可用 `php74 vendor/bin/phpunit` 确认旧测试基线，升级后统一使用 `php`
