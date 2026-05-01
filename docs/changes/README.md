# Changes

本目录用于记录项目的**版本级变更日志**与**spec 归档**。

---

## 目录结构

```
docs/changes/
├── CHANGELOG.md              # 全局摘要（只含已 release 的版本，每版几行关键信息）
├── unreleased/               # 已完成但未 release 的 feature 变更记录
│   └── <feature-name>.md     # 每个 feature 一个文件
├── <version>/                # 已 release 的版本目录
│   ├── CHANGELOG.md          # 该版本的详细变更记录
│   └── specs/                # 该版本归档的 specs
│       └── <feature-name>/   # 从 .kiro/specs/ 移入
```

---

## 流程

### Feature 完成时（合并回 develop）

- 在 `docs/changes/unreleased/` 下创建 `<feature-name>.md`
- 记录该 feature 的详细变更（Added / Changed / Fixed 等）

### Release 时

1. 创建 `docs/changes/<version>/` 目录
2. 将 `unreleased/` 下本次 release 验证通过的 feature 变更记录合并整理为 `docs/changes/<version>/CHANGELOG.md`
3. 将已完成的 specs 从 `.kiro/specs/` 移入 `docs/changes/<version>/specs/`
4. 在全局 `CHANGELOG.md` 顶部追加该版本的摘要（几行即可）
5. 清除 `unreleased/` 中本次 release 验证通过的 feature 变更记录（未验证的保留）

---

## 全局 CHANGELOG.md 格式

```md
# Changelog

## v0.2 - 2026-03-31

一句话摘要。详见 [0.2/CHANGELOG.md](0.2/CHANGELOG.md)。

## v0.1 - 2026-03-20

一句话摘要。详见 [0.1/CHANGELOG.md](0.1/CHANGELOG.md)。
```

简洁摘要，详细内容见各版本目录下的 CHANGELOG.md。

---

## 版本 CHANGELOG.md 格式

```md
# Changelog v0.2

本文件记录 v0.2 release 的变更内容。

---

## 包含的 Feature

### Feature 名称（PRP-xxx）

- 变更点 1
- 变更点 2

---

## 修复的 Issue

- [ISS-xxx](fixed/ISS-xxx.md)：问题描述

---

## 工程变更

- 变更点

---

## 测试覆盖

- 测试统计
```

---

## 设计原则

- `unreleased/` 按 feature 拆文件，避免并行开发时冲突
- 每个 release 是自包含目录（changelog + specs 归档）
- 全局 CHANGELOG.md 保持简洁，只做索引
- spec 归档后从 `.kiro/specs/` 移除，不再作为系统事实来源
