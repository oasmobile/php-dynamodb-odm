---
inclusion: manual
description: Spec gatekeeper 校验 requirements 阶段的详细指引。由 spec-gatekeeper 在校验 requirements.md 时读取。
---

# Requirements Gatekeep 指引

本文件定义 requirements.md 的校验标准。Gatekeeper 按以下清单逐项检查，发现问题直接修正。

---

## 执行顺序

1. 机械扫描
2. 结构校验
3. 术语表校验
4. Requirement 条款校验
5. Socratic Review 校验
6. 目的性审查
7. 将修正项写入 Gatekeep Log
8. 生成 Clarification Round（为 design 阶段准备）
9. Completion：向 main-agent 返回结果

---

## 1. 机械扫描

在所有语义校验之前，先执行机械扫描：

- [ ] 无 TBD / TODO / 待定 / 占位符
- [ ] 无空 section 或不完整的列表
- [ ] 内部引用一致（requirement 编号、术语表中的术语在正文中使用）
- [ ] 无 markdown 格式错误（未闭合的代码块、错误的标题层级）

发现问题直接修正，不需要在 Gatekeep Log 中逐一列出机械扫描的修正。

---

## 2. 结构校验

requirements.md 必须包含以下 section，且顺序正确：

| 序号 | Section | 必要性 | 说明 |
|------|---------|--------|------|
| 1 | `# Requirements Document` | 必须 | 一级标题，下方一句话说明本文件定位和所属 spec 目录 |
| 2 | `## Introduction` | 必须 | 一段话说明 feature 范围，明确列出不涉及的内容 |
| 3 | `## Glossary` | 必须 | 术语表，`- **Term**: 定义` 格式 |
| 4 | `## Requirements` | 必须 | 需求条款，每条为 `### Requirement N: 名称` |
| 5 | `## Socratic Review` | 推荐 | 自问自答式审查（系统可能未生成，gatekeeper 应补充） |

Release spec 结构不同，必须包含以下 section：

| 序号 | Section | 必要性 | 说明 |
|------|---------|--------|------|
| 1 | `# Release <version> Requirements` | 必须 | 一级标题，一句话说明本文件定位 |
| 2 | `## 发布范围` | 必须 | 表格列出本次 release 包含的 feature（Feature 名称、Spec 路径、Proposal 引用、Proposal Status、Tasks 完成状态） |
| 3 | `## Feature 概要` | 必须 | 每个 feature 一段话概述核心能力和关键实现 |
| 4 | `## 已知 Issue 评估` | 必须 | 评估项目级 Issue 对本次发布的影响 |
| 5 | `## 发布判定` | 必须 | 检查项表格 + 结论 |

### 检查项

- [ ] 一级标题存在且正确
- [ ] Introduction 存在，描述了 feature 范围
- [ ] Introduction 明确了不涉及的内容（Non-scope）
- [ ] Glossary 存在且非空
- [ ] Requirements section 存在且包含至少一条 requirement
- [ ] 各 section 之间使用 `---` 分隔

---

## 3. 术语表校验

- [ ] Glossary 中的术语在正文 AC 中被实际使用（无孤立术语）
- [ ] AC 中使用的领域概念在 Glossary 中有定义（无未定义术语）
- [ ] 术语格式为 `- **Term**: 定义`（加粗术语名 + 冒号 + 定义）

---

## 4. Requirement 条款校验

每条 requirement 必须包含：

```markdown
### Requirement N: 名称
**User Story:** 作为 <角色>，我希望 <能力>，以便 <价值>。
#### Acceptance Criteria
1. THE <Subject> SHALL ...
```

User Story 应使用中文行文：`作为 <角色>，我希望 <能力>，以便 <价值>`。如果原文为英文，校验时改为中文。

### AC 语体校验

- [ ] 使用 `THE <Subject> SHALL ...` 描述必须具备的行为
- [ ] 使用 `WHEN <条件> THEN THE <Subject> SHALL ...` 描述触发条件下的行为
- [ ] 使用 `IF <条件> THEN THE <Subject> SHALL ...` 描述异常/边界条件下的行为
- [ ] Subject 使用 Glossary 中定义的术语（大写下划线形式，如 `Batch_Handler`、`Selected_Record`）
- [ ] AC 编号连续（1, 2, 3...），无跳号

### 内容边界校验

这是最重要的校验项。Requirements 应聚焦外部可观察行为，不应包含实现细节。

**不应出现的内容**（发现则修正或移除）：

- 具体库名、框架名（除非 proposal 已明确选型，此时引用能力而非库名）
- 内部结构描述（泛型接口、类型参数、包结构、文件名）
- 实现策略（目录自动创建、注释形式声明依赖、具体算法步骤）
- 具体的类名、方法签名、函数名

**可以出现的内容**：

- 质量属性（round-trip 正确性、存储可替换性、错误描述性）
- 外部可观察的行为约束
- 使用 Glossary 中定义的领域术语引用系统组件

> **注意**：这条规则需要灵活判断。如果 Glossary 中已定义了某个术语（如 `Batch_Handler`、`EventDispatcher`），在 AC 中使用该术语是合理的——它是领域概念而非实现细节。判断标准是：该术语是否在 Glossary 中有明确定义，且在 AC 中作为 Subject 使用。

---

## 5. Socratic Review 校验

Kiro 系统生成的 spec 通常不包含 Socratic Review section，gatekeeper 应补充。

Socratic Review 同时承担 clarification 职责——通过自问自答审视文档中模糊或可能有多种理解的地方。

至少覆盖以下维度：

- 每条 requirement 是否都在描述外部可观察的行为？是否混入了实现细节？
- 是否有遗漏的场景？（错误路径、边界条件、并发、幂等性等）
- 各 requirement 之间是否存在矛盾或重叠？
- 是否有隐含的前置假设没有显式列出？
- 与 proposal 的 scope / non-goals 是否一致？是否越界或缩水？（如有 proposal）
- scope 边界是否清晰？是否存在模糊地带，不同理解会导致不同的 design？

如果已有 Socratic Review，检查其覆盖度是否充分，不充分则补充。

---

## 6. 目的性审查

完成逐项校验后，退后一步，审视文档整体是否达到了 requirements 阶段的目的。

Requirements 的核心目的是：**让读者（包括后续 design 阶段的 agent）清楚地知道要做什么、不做什么、以及做到什么程度算完成**。

### 审查清单

- [ ] **Goal CR 回应**：如果 goal.md 中存在 Clarification Round 且用户已回答，检查 requirements 是否体现了用户在 goal CR 中做出的决策。未体现的决策应补充到对应的 requirement 或 Introduction 中。
- [ ] **Goal 清晰度**：文档是否清楚传达了这个 feature 要解决的问题和期望达到的效果？读完 Introduction 后，读者能否用一句话概括 feature 的目标？
- [ ] **Non-goal / Scope 边界**：文档是否明确了不涉及的内容？是否存在模糊地带，可能导致 design 阶段做多或做少？
- [ ] **完成标准**：AC 整体是否构成了充分的验收条件？如果所有 AC 都满足，feature 是否就算完成了？是否有遗漏的关键场景？
- [ ] **可 design 性**：仅凭这份 requirements，design 阶段的 agent 是否有足够信息开始技术方案设计？是否存在关键信息缺失（如外部系统约束、数据格式、性能要求）？

如果发现文档在上述任一维度不达标，直接修正。修正后在 Gatekeep Log 中记录修正项（修正类型为 `目的`）。

---

## 7. Gatekeep Log

将校验过程中的修正项写入 requirements.md 末尾的 `## Gatekeep Log` section。

---

## 8. Clarification Round (CR)

校验和修正全部完成后，生成面向 design 阶段的 CR 问题。

阅读 requirements.md、goal.md（如存在）及相关 SSOT，提出 **3 个以上**的澄清问题。

CR 聚焦 **requirements 到 design 的衔接**——requirements 中哪些行为描述存在多种合理的实现路径，需要用户在进入 design 前做出决策。**不应**问 requirements 自身产出物的格式问题（AC 写法、requirement 拆分方式、术语表结构等），那些已在校验阶段处理。也**不应**重复 goal.md 中已澄清过的问题。

聚焦方向：
- AC 中是否存在多种合理的 design 路径？（如同一行为可以用不同架构模式实现，需要用户倾向）
- 非功能约束是否足够明确？（性能、并发、幂等性、错误恢复策略等，这些直接影响 design 选型）
- 各 requirement 之间的交互是否有未显式约定的行为？（如并发场景、执行顺序、资源竞争）
- 是否有隐含的技术约束需要确认？（如对现有架构的兼容性、外部系统的限制）
- 边界条件的处理策略是否需要用户决策？（如空集合、超时、部分失败）

**不应涉及的方向**：
- scope 边界、兼容性策略的"做不做"问题——这些属于 goal CR 的范畴，应已在 goal.md 中澄清
- 实现顺序、task 拆分方式、测试策略——这些属于 gk-design CR 的范畴

每个问题提供 **至少 3 个选项**（可附加一个开放选项）。

将 CR 题目和选项写入 Gatekeep Log 的 `### Clarification Round` 小节：

```markdown
### Clarification Round

**状态**: 待用户回答

**Q1:** <问题文本>
- A) <选项 A>
- B) <选项 B>
- C) <选项 C>
- D) 其他（请说明）

**A:** （待填写）

**Q2:** <问题文本>
...
```

---

## 9. Completion

Gatekeeper 完成所有校验、修正和 CR 生成后，向 main-agent 返回以下内容：

1. **校验结果摘要**：通过 / 已修正后通过，列出修正项（如有）
2. **CR 待确认**：告知 main-agent requirements.md 的 Gatekeep Log 中有待用户回答的 CR 问题

Main-agent 收到后：
1. 将校验结果告知用户
2. 读取 requirements.md 中 Gatekeep Log 的 `### Clarification Round` 小节
3. **逐题**与用户交互——每次只问一个问题，使用 `userInput` 工具提问，等回答后再问下一个
4. 将用户回答写入对应的 `**A:**` 行
5. 所有问题回答完毕后，将 `**状态**` 更新为 `已完成`
