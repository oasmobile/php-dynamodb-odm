---
inclusion: manual
description: 生成 spec goal.md 时读取。定义 goal.md 的生成流程、内容结构和 clarification 规范。
---

# Spec Goal 指引

本文件定义 `goal.md` 的生成流程。`goal.md` 是 spec 工作流的起点文档，在分支创建后、requirements 编写前生成，用于固化本次要做的内容和关键决策。

---

## 触发时机

- Feature / Release / Hotfix 分支创建完成后

---

## 生成流程

### 1. 分析本分支的需求来源

- **Feature 分支**：读取 proposal 文件，提取 scope、goals、non-goals
- **Hotfix 分支**：读取关联的 issue 或 note 文件，提取问题描述、影响范围、期望修复行为
- **Release 分支**：读取本次 release 包含的各 feature 的 proposal 和已完成的 spec，提取整体发布范围和关键变更

### 2. 分析 SSOT

- 读取 `docs/state/` 下的所有文件，建立对系统当前状态的理解
- 基于上一步获取的需求来源，重点关注相关模块

### 3. Clarification

基于 SSOT 理解和需求来源，向用户提出 **至少 3 个** clarification 问题，逐个询问。

CR 聚焦 **需求来源到 requirements 的衔接**——在 proposal / issue / note 的基础上，澄清 scope 和意图层面的模糊点，确保 requirements 编写时有清晰的输入。

**聚焦方向：**
- scope 边界是否清晰？（哪些在范围内、哪些不在，是否有模糊地带）
- 用户的业务场景理解是否有歧义？（同一句话可能有多种理解）
- 是否有遗漏的用户场景或角色？（从用户意图出发）
- 兼容性策略：是否需要兼容旧行为 / 旧数据？（做不做的问题，不涉及怎么兼容）
- 优先级或 MVP 范围：如果 scope 较大，是否需要分阶段？

**不应涉及的方向**（这些属于后续 CR 的范畴）：
- 非功能约束的细节（性能指标、并发模型、幂等性等）——留给 gk-requirements CR
- 具体的行为边界条件处理策略（空集合、超时、部分失败等）——留给 gk-requirements CR
- 技术选型或架构偏好——留给 gk-design CR
- 实现顺序或 task 拆分方式——留给 gk-design CR

**问题设计原则：**
- 每个问题聚焦一个决策点
- 每个问题提供 **至少 3 个** 具体选项（A / B / C / ...）
- 每个问题的最后一个选项固定为：**「补充说明（请描述）」**——允许用户给出选项之外的回答
- 问题应基于 SSOT 与需求之间的 gap 或模糊地带，而非重复需求中已明确的内容

**交互方式：**
- 使用 `userInput` 工具**逐个**向用户提问（一次只问一个问题）
- 等待用户回答后再提出下一个问题
- 如果用户的回答引发了新的疑问，可以追加问题（但不要无限追问，控制在合理范围内）

### 4. 生成 goal.md

Clarification 完成后，在 `.kiro/specs/<name>/goal.md` 中生成目标文档。

---

## 文件路径

- `<name>` 由分支类型和分支名决定：
  - Feature 分支 `feature/foo-bar` → `<name>` = `foo-bar`
  - Hotfix 分支 `hotfix/0.3.1` → `<name>` = `hotfix-0.3.1`
  - Release 分支 `release/0.4` → `<name>` = `release-0.4`
- 如果 `.kiro/specs/<name>/` 目录下已有其他 spec 文件（requirements.md 等），goal.md 与它们共存，不覆盖

---

## goal.md 内容结构

```markdown
# Spec Goal: <标题>

## 来源

- 分支: `<branch-name>`
- 需求文档: `<proposal/issue/note 文件路径>`

## 背景摘要

<从 SSOT 和需求来源中提炼的简要背景，2-4 段>

## 目标

<本次要实现的内容，bullet list>

## 不做的事情（Non-Goals）

<明确排除的内容，bullet list；如果需求来源中已有 non-goals 则沿用，否则基于 clarification 结果补充>

## Clarification 记录

<逐条记录每个问题和用户的回答>

### Q1: <问题>

- 选项: A) ... / B) ... / C) ... / 补充说明
- 回答: <用户选择或描述>

### Q2: <问题>

...

## 约束与决策

<从 clarification 中提炼出的关键决策和约束，作为后续 spec 编写的输入>
```

---

## 完成后

生成 goal.md 后，告知用户：
- 文件路径
- 简要说明内容概要
- 建议下一步：开始编写 spec（requirements → design → tasks）
