# Changelog v2.0

本文件记录 v2.0 release 的变更内容。

---

## 包含的 Feature

### Modernize & Harden（PRP-001）

**依赖升级**

- PHP 最低版本从 7.1 升级到 8.5
- PHPUnit 从 ^5.7 升级到 ^13.0
- `symfony/console` 从 ^4.4 升级到 ^7.0
- `symfony/finder` 从 ^4.4 升级到 ^7.0
- `oasis/aws-wrappers` 升级到 ^3.0
- `oasis/logging` 升级到 ^3.0
- 移除 `doctrine/annotations` 和 `doctrine/common`
- 新增 `giorgiosironi/eris` ^1.0（dev）

**Attribute 迁移**

- 四个注解类（Field、Item、Index、PartitionedHashKey）从 doctrine docblock 注解迁移到 PHP 8 原生 `#[Attribute]`
- 目录从 `src/Annotations/` 重命名为 `src/Attributes/`
- 命名空间从 `Oasis\Mlib\ODM\Dynamodb\Annotations` 改为 `Oasis\Mlib\ODM\Dynamodb\Attributes`

**解析层重写**

- `ItemReflection::parse()` 从 `Reader` 接口改为 `ReflectionClass::getAttributes()` / `ReflectionProperty::getAttributes()`
- 方法签名从 `parse(Reader $reader)` 变为 `parse(): void`

**构造函数简化**

- `ItemManager::__construct()` 从 4 参数简化为 2 参数，移除 `$cacheDir` 和 `$isDev`
- 移除 `getReader()` 和 `loadAnnotationClass()` 方法

**PHP 8.x 语法升级**

- 全部 `src/` 文件使用 typed properties、constructor promotion、readonly、match、union types、返回类型声明
- 移除 `setAccessible(true)` 调用
- `str_contains` / `str_starts_with` 替代 `strpos` 模式

**测试体系建设**

- 三层 test suite：unit / integration / pbt
- 197 个单元测试，mock Connection 隔离
- 7 个 PBT 测试（Eris），覆盖核心数据路径不变量
- 集成测试覆盖完整 CRUD、Query/Scan、CAS、PartitionedHashKey、Schema 工具

---

## 修复的 Issue

无独立 issue 文件。Release stabilize 阶段发现的覆盖率目标未达标问题（P3 trivial）已确认接受。

---

## 工程变更

- `phpunit.xml` 升级到 PHPUnit 13 schema，配置三个 test suite（unit / integration / pbt）
- 新增 `phpunit-phase0.xml`（Phase 0 旧环境测试配置，已完成使命）
- 测试目录新增 `ut/Unit/`、`ut/Integration/`、`ut/PBT/` 子目录
- 新增 `docs/manual/migration-v2.md` 迁移指南

---

## Bug Fix

- `Index::getKeys()` 修复：range key 判断从 truthy check 改为严格空字符串比较（`$this->range !== ''`），修复 range 为 `"0"` 时被错误忽略的问题

---

## 测试覆盖

- Unit + PBT：197 tests, 370 assertions（unit）+ 7 tests, ~47000 assertions（pbt）
- 全部 suite 合并行覆盖率 85.74%
