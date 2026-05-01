# Proposals

本目录用于管理项目中的 **proposal**，即对某项功能、改动或工作方向的正式需求提案。

proposal 的作用，不是直接描述实现方案，也不是任务拆解，而是先明确：

- 为什么要做
- 想解决什么问题
- 目标与范围是什么
- 是否值得进入实施
- 当前处于什么生命周期阶段

## proposal 是什么

proposal 是需求意图（intent）的主记录。

它可以对应：

- 一个新 feature
- 一次重要改进
- 一项重构提议
- 一个跨迭代持续推进的工作主题

proposal 可以早于某次迭代被创建，也可以在实施过程中持续更新状态，直到最终完成、被拒绝，或被新的 proposal 替代。

## proposal 不是什么

proposal 不是：

- backlog 排期工具
- 具体技术设计文档
- 任务执行清单
- 当前系统状态文档
- 已完成变更记录

这些分别应由其他层负责：

- `docs/state/`：当前系统状态（SSOT）
- `.kiro/specs/`：单次 feature / iteration 的 requirements、design、tasks
- `docs/changes/`：已完成变更的语义记录
- 外部系统（如 Jira / Linear / Notion）：排期、优先级、版本计划、看板视图

## 内容边界

proposal 只描述用户可见的行为与意图，不涉及内部实现细节。

### 应包含

- 用户可感知的命令、参数、输出格式
- 错误场景下的用户可见行为（如错误提示、exit code）
- 功能边界与约束（Goals / Non-Goals / Scope）

### 不应包含

- 内部接口的返回值定义或数据结构设计
- 持久化方式、文件格式等技术选型
- 代码层面的架构与实现决策

这些属于实现层面的决策，应在 `.kiro/specs/` 的 design 阶段定义。

---

## proposal 的生命周期

每个 proposal 应明确状态。建议使用以下状态：

- `draft`：草稿中，问题与范围尚未定型
- `accepted`：已接受，允许进入实施准备
- `in-progress`：已进入实施过程，通常已有对应 spec 或开发工作
- `implemented`：feature 已完成并合并回 develop，但尚未经过 release 验证
- `released`：已随 release 正式发布
- `rejected`：已明确不做
- `superseded`：已被其他 proposal 替代

### Socratic Review（draft → accepted Gate）

proposal 从 `draft` 转为 `accepted` 之前，agent 必须进行一轮 Socratic Review——以自问自答的形式，对 proposal 内容进行挑战性审视。

agent 扮演 challenger 角色，至少覆盖以下维度：

- 这个问题是否真的需要解决？有没有更简单的替代方案？
- Scope 是否过大或过小？Non-Goals 中是否有应该纳入的项？
  - 当 proposal scope 涵盖多个独立子系统或可独立交付的能力时，建议拆分为多个 proposal
  - 拆分信号：子系统之间没有运行时依赖、可以独立测试和发布、由不同的用户场景驱动
  - agent 应向用户建议拆分方案，由用户决定是否拆分
- 对现有架构的影响是否被充分考虑？（粗粒度评估，详细的 Impact Analysis 在 spec design 阶段完成）
- 是否有隐含的依赖或前置条件没有列出？

执行方式：

- agent 自行提出问题并尝试回答，在对话中呈现给用户
- 如果在自问自答过程中发现 proposal 的漏洞或不一致，agent 应主动修正 proposal 内容
- 用户审阅后决定是否 accept
- Socratic Review 的过程不写入 proposal 文件——proposal 只保留修正后的最终内容
- 用户可以说"我已经想清楚了，直接 accept"来跳过此环节

## proposal 与其他目录的关系

### 与 `docs/state/` 的关系
`docs/state/` 记录当前系统事实，是项目级 SSOT。  
proposal 不能替代 state。proposal 描述的是"想做什么 / 为什么做"，不是"系统现在是什么"。

### 与 `.kiro/specs/` 的关系
当一个 proposal 被接受并进入具体实施时，应产生对应的 feature spec。  
一个 spec 通常对应一次明确的 feature / iteration，而一个 proposal 可以先于 spec 存在，也可以跨越多个实施阶段。

### 与 `docs/changes/` 的关系
当 proposal 对应的工作真正完成后，应在 `docs/changes/` 中留下变更记录。  
proposal 回答"为什么做"，change 回答"实际改了什么"。

### 与外部 backlog / roadmap 系统的关系
排期、优先级、版本规划、看板管理，建议放在外部系统中。  
repo 内的 proposal 负责保存需求语义、上下文与可追溯关系，而不是承担项目管理工具的职责。

## 建议内容结构

一个 proposal 文件建议至少包含：

- 标题
- Status
- Background
- Problem
- Goals
- Non-Goals
- Scope
- References
- Notes

可根据项目需要补充：

- Alternatives considered
- Risks
- Open questions
- Related proposals
- Related specs
- Related changes

## 命名建议

单个 proposal 文件建议命名为：

- `PRP-001-<slug>.md`
- `PRP-002-<slug>.md`

例如：

- `PRP-001-user-management.md`
- `PRP-002-report-export.md`

## 使用原则

- proposal 应先于详细设计存在
- proposal 应独立于单次迭代
- proposal 进入实施后，应关联对应 spec
- proposal 完成后，应关联对应 change
- proposal 不应被当作当前系统状态文档使用

---

## Proposal 归档

随着项目推进，`docs/proposals/` 中会积累大量已完成或已废弃的 proposal。为保持目录可读性：

- 归档目录：`docs/proposals/archive/`
- 归档对象：状态为 `released`、`rejected`、`superseded` 的 proposal
- 归档时机：
  - `rejected`、`superseded`：状态变更后可在 develop 分支上立即归档，无需等待 release finish
  - `released`：release finish 收敛阶段，与 spec 归档同步进行
- 归档方式：`mv`（不允许 `cp`），保留原文件名
- 归档后 proposal 内容不再修改，仅作为历史记录保留
