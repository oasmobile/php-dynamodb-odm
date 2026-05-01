# Issue Management

本目录为项目级 issue 存储，记录已确认但未在当前迭代中修复的问题。

---

## Issue 系列总览

| 系列 | 含义 | 来源 | Found In | 存放位置 |
|------|------|------|----------|----------|
| `L` | 已发布版本的已有 bug | 任何阶段偶然发现 | 已发布版本 tag | `issues/` |
| release | release stabilize 阶段发现的问题 | release 测试阶段 | alpha/beta tag | `.kiro/specs/release-*/issues/` |

- `L` 独立体系，有自己的版本 tag 归属
- release issue 在 stabilize 阶段产生，finish 时归档或移回项目级
- feature 开发和 develop 集成阶段发现的问题，如果不是已发布版本的 bug，统一记为 notes（`docs/notes/`），不走 issue 流程

---

## Issue 生命周期

```text
发现 → 记录 → 评估 → 修复或延后 → 归档或转换
```

### L 系列（线上问题）

- 不管在哪个阶段发现（feature 开发、develop 集成、release 测试），只要 bug 在已发布版本中就存在，就是 `L`
- 直接记录在 `issues/` 根目录，Found In 使用已发布版本 tag
- 如果在 release 中被修复，Fixed In 填预判的下一个 alpha/beta tag，release finish 时参与统一 tag review（alpha/beta → 正式 release tag）

### Release Issue（stabilize 阶段）

- release stabilize 阶段手工测试中发现的问题
- 记录在 `.kiro/specs/release-*/issues/`
- 修复流程、severity 门槛等详见 release-workflow steering

### Release Finish 时的 Issue 收敛

- release issues 中已修复的（`closed`）：mv 到 `docs/changes/<version>/fixed/`
- release issues 中未修复的（`open`）：mv 到本目录，保留原 ID
- 项目级 `issues/` 中在本次 release 修复的（`closed`）：mv 到 `docs/changes/<version>/fixed/`

---

## Issue ID 规则

| 系列 | 格式 | 示例 |
|------|------|------|
| L（线上问题） | `ISS-<版本>-L<两位序号>` | `ISS-0.1.1-L01` |
| release stabilize | `ISS-<版本>-<三位序号>` | `ISS-0.2-001` |

- `L` 的版本号为发现该 bug 所属的已发布版本
- release 阶段的版本号与 release 分支对应
- 移入项目级 `issues/` 或归档时保留原 ID，不重新编号

文件命名格式：`<issue-id>-<简短描述>.md`

示例：
- `ISS-0.1.1-L01-env-var-cannot-override.md`
- `ISS-0.2-001-auth-token-expired.md`

---

## Found In / Fixed In 规则

| 系列 | Found In | Fixed In |
|------|----------|----------|
| `L` | 已发布版本 tag（如 `v0.1.1`） | 预判下一个 alpha/beta tag；release finish 时参与统一 tag review |
| release | alpha/beta tag | 预判下一个 alpha/beta tag |

- `L` 系列 Found In 始终使用已发布版本 tag；Fixed In 在 release 中修复时填预判的 alpha/beta tag
- release finish 阶段统一 tag review：将所有本次 release 修复的 issue 的 Found In 和 Fixed In 从 alpha/beta tag 替换为正式 release tag

---

## 目录结构

```
issues/
├── README.md
├── ISS-0.1.1-L01-aaa.md      # L 系列（线上问题）
└── ISS-0.2-003-bbb.md        # release 未修复，移回项目级
```

---

## Severity 定义与发布门槛

| 级别 | 含义 | 发布门槛 |
|------|------|----------|
| `[P0] critical` | 核心功能不可用，阻塞发布 | 必须修复，阻塞发布 |
| `[P1] major` | 重要功能异常 | 必须修复，阻塞发布 |
| `[P2] minor` | 非核心功能异常或体验问题 | 需用户确认是否可接受带 issue 发布 |
| `[P3] trivial` | 文档、格式等极低影响问题 | 可忽略，不阻塞发布 |

---

## Issue 文件规范

### 必填字段

| 字段 | 说明 |
|------|------|
| Title | 问题标题 |
| Severity | `[P0] critical` / `[P1] major` / `[P2] minor` / `[P3] trivial` |
| Status | `open` / `in-progress` / `closed` |
| Found In | 见上方 Found In 规则 |
| Fixed In | 见上方 Fixed In 规则；未修复则留空 |
| Related Test | 关联的手工测试项（如 `tasks.md 4.2`）；无则留空 |

### 必填章节

- Description：问题描述
- Steps to Reproduce：复现步骤
- Expected Behavior：期望行为
- Actual Behavior：实际行为
- Analysis：原因分析（可选，已知时填写）
- History：事件记录（按时间倒序）

### History 格式

```markdown
## History

- `2026-03-26T09:00Z` `v0.2-alpha1` [发现] release/0.2 stabilize 阶段发现认证 token 过期问题
- `2026-03-26T15:00Z` `v0.2-alpha2` [修复] 在 release/0.2 分支修复，commit abc1234
- `2026-03-27T10:00Z` `v0.2-beta1` [关闭] 验证通过，标记为 closed
```

- 格式：`` `UTC时间` `tag` [事件类型] 描述 ``
- 时间使用 UTC，格式为 `YYYY-MM-DDThh:mmZ`（ISO 8601 简写）
- tag：`L` 系列填已发布版本 tag；release 系列填最近的 alpha/beta tag
- 事件类型：`发现` / `分析` / `修复` / `关闭` / `重开` / `延后` / `移入项目级`

---

## 归档目录结构

```
docs/changes/<version>/
├── CHANGELOG.md
├── specs/          # 归档的 feature specs
└── fixed/          # 本次 release 修复的 issue
    ├── ISS-0.2-001-xxx.md
    └── ISS-0.1.1-L01-yyy.md
```
