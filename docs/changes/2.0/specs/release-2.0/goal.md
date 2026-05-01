# Spec Goal: Release 2.0 — Modernize & Harden

---

## 来源

- 分支: `release/2.0`
- 需求文档: `docs/proposals/PRP-001-modernize-and-harden.md`

---

## 背景摘要

当前项目基于 PHP 7.1 / PHPUnit 5.7 构建，依赖版本陈旧（`doctrine/annotations` ^1.4、`symfony/console` ^4.4、`doctrine/common` ^2.7 等）。`doctrine/annotations` 已被官方标记为 abandoned，不再维护。测试仅有一个集成测试文件 `ut/ItemManagerTest.php`，依赖真实 AWS DynamoDB 环境，无法离线运行，缺少隔离单元测试和 PBT。

PHP 8.5 已发布，PHPUnit 13 是当前主流版本。继续停留在旧版本会导致无法使用 PHP 8.x 语言特性、依赖库不再维护安全补丁、新开发者上手成本高。

`docs/state/architecture.md` 记录了当前系统的分层结构、核心类职责、对象生命周期、注解体系、DBAL 抽象、CAS 机制、CLI 工具和异常体系。本次升级需要在保持架构不变的前提下，全面现代化技术栈和代码风格。

---

## 目标

- 将 PHP 最低版本升级到 8.5，PHPUnit 升级到 13
- 升级所有 Composer 依赖到兼容 PHP 8.5 的最新稳定版
- **移除 `doctrine/annotations` 和 `doctrine/common` 依赖，将注解系统迁移到 PHP 8 原生 Attributes**（扩展自 PRP-001 原始 scope）
- 将源代码语法全面升级到 PHP 8.x 风格（typed properties、constructor promotion、match、readonly、`str_contains`/`str_starts_with`、移除不必要的 `setAccessible(true)` 等）
- 简化 `ItemManager` 构造函数：移除 `$cacheDir` 和 `$isDev` 参数
- 补齐隔离单元测试（mock Connection），覆盖核心类、Annotations、Console Commands、Schema Tools、Exceptions
- 保证单元测试全部通过
- 引入 PBT（`giorgiosironi/eris` ^1.0），对核心数据路径进行属性测试
- 单元测试 + PBT 行覆盖率 ≥ 85%
- 补齐集成测试，覆盖完整 CRUD 生命周期等场景，集成测试覆盖率 ≥ 75%
- 更新 `phpunit.xml` 配置，分离 unit / integration / pbt 三个 test suite
- 更新 `PROJECT.md` 和 `docs/state/architecture.md` 反映新版本信息

---

## 不做的事情（Non-Goals）

- 不改变公共 API 的方法签名语义（参数类型、返回类型可以收紧）
- 不引入新的功能特性
- 不改变项目的目录结构（`src/`、`ut/` 保持不变）
- 不处理 `oasis/aws-wrappers` 和 `oasis/logging` 的内部升级（仅升级版本约束）
- 不引入 CI/CD 配置
- `Connection` 接口声明的 `__construct` 不做改变（保持 API 兼容）

---

## Clarification 记录

### Q1: PHP 8.x 语法升级的范围

- 选项: A) 全面升级 / B) 保守升级 / C) 分层升级 / D) 补充说明
- 回答: **A — 全面升级**，所有列出的 PHP 8.x 语法特性都尽量应用，一次到位

### Q2: `doctrine/annotations` 已废弃，注解系统的处理方式

- 选项: A) 继续用 ^2.0 / B) 迁移到原生 PHP Attributes / C) 补充说明
- 回答: **B — 迁移到原生 PHP Attributes**，移除 `doctrine/annotations` 依赖

### Q3: `ItemManager` 构造函数中 `$cacheDir` 和 `$isDev` 参数的处理

- 选项: A) 直接移除 / B) 保留但标记 deprecated / C) 补充说明
- 回答: **A — 直接移除**，简化 API，同时确保原生 Attributes 能完整覆盖当前 docblock 注解的所有功能

### Q4: `oasis/aws-wrappers` 和 `oasis/logging` 的 PHP 8.5 兼容性

- 选项: A) 放宽版本约束 / B) Fork 修复 / C) 记为 known issue / D) 补充说明
- 回答: **D — 所有 oasis 库的最新大版本都支持 8.x**，直接升级到最新兼容版本即可，此风险项消除

### Q5: `giorgiosironi/eris` 的版本约束

- PRP-001 中写的是 `^1.0`，用户确认为 `1.x`（`^1.0` 约束本身匹配 1.x 全系列）
- eris 最新版支持 PHP 8.1-8.4、PHPUnit 10-13，PHP 8.5 大概率兼容

---

## 约束与决策

1. **注解迁移到 Attributes**：`@Item`、`@Field`、`@Index`、`@PartitionedHashKey` 全部改为 `#[Item]`、`#[Field]`、`#[Index]`、`#[PartitionedHashKey]` 原生 Attributes；`ItemReflection::parse()` 从 `Reader` 接口改为反射 API 读取 Attributes；移除 `doctrine/annotations`、`doctrine/common` 依赖
2. **构造函数简化**：`ItemManager::__construct()` 移除 `$cacheDir` 和 `$isDev` 参数，移除 `AnnotationRegistry::registerLoader()` 和 `CachedReader` / `FilesystemCache` 相关代码
3. **语法升级全面覆盖**：所有 `src/` 文件统一升级，不分层
4. **oasis 依赖无风险**：直接升级版本约束
5. **PBT 使用 eris ^1.0**：覆盖 hydrate/dehydrate 往返、脏检查、CAS 条件生成、PartitionedHashKey 计算
