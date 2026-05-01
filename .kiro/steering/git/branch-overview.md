---
inclusion: auto
description: 当需要了解 GitFlow 分支模型、各分支定位（develop/master/feature/release/hotfix）时读取
---

# Branch Overview

GitFlow 分支模型的总览与各分支定位。

---

## 分支定位

| 分支 | 定位 | 来源 |
|------|------|------|
| `master` | 已发布版本，文档与代码完全一致 | release / hotfix merge |
| `develop` | 持续集成，文档逐步补齐 | feature merge |
| `feature/*` | 功能开发，从 develop 创建，命名与 `<spec-dir>/<feature-name>/` 一致 | develop |
| `release/*` | 发布准备，从 develop 创建 | develop |
| `hotfix/*` | 紧急修复已发布版本的问题，从 master 创建 | master |

---

## Git 历史语义

- Git 历史用于表达 branch lifecycle，而不仅是记录 diff
- 必须保留 feature / release / hotfix 的拓扑结构
- 不允许压平成线性历史
