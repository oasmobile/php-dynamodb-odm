---
inclusion: manual
description: Spec gatekeeper 校验 design 阶段的详细指引。由 spec-gatekeeper 在校验 design.md 时读取。
---

# Design Gatekeep 指引

本文件定义 design.md 的校验标准。Gatekeeper 按以下清单逐项检查，发现问题直接修正。

---

## 执行顺序

0. 架构上下文加载（如 `graphify_ready`）
1. 机械扫描
2. 结构校验
3. Requirements 覆盖校验
4. Impact Analysis 校验
5. 技术方案质量校验
6. Socratic Review 校验
7. 目的性审查
8. 将修正项写入 Gatekeep Log
9. 生成 Clarification Round（为 tasks 阶段准备）
10. Completion：向 main-agent 返回结果

---

## 0. 架构上下文加载

如果 `graphify_ready`，优先使用 graphify 子命令（`graphify query`、`graphify explain`、`graphify path` 等）进行结构化查询，而不是直接读取源文件。后续步骤（特别是 §4 Impact Analysis 和 §5 技术方案质量）可利用其中的模块依赖关系、god node、community 结构来辅助判断。具体用法参见 `graphify.md` steering。

---

## 1. 机械扫描

在所有语义校验之前，先执行机械扫描：

- [ ] 无 TBD / TODO / 待定 / 占位符
- [ ] 无空 section 或不完整的列表
- [ ] 内部引用一致（requirements 编号、术语引用）
- [ ] 代码块语法正确（语言标注、闭合）
- [ ] 无 markdown 格式错误

发现问题直接修正，不需要在 Gatekeep Log 中逐一列出机械扫描的修正。

---

## 2. 结构校验

Feature / Hotfix 的 design.md 应包含以下关键 section（顺序可灵活调整，但必须存在）：

| Section | 必要性 | 说明 |
|---------|--------|------|
| 一级标题 | 必须 | 说明本文件定位 |
| 技术方案主体 | 必须 | 承接 requirements，给出具体技术方案 |
| 接口 / 数据模型定义 | 必须 | 接口签名、数据模型、模块划分 |
| `## Impact Analysis` | 必须 | 影响分析 |
| `## Alternatives Considered` | 推荐 | 备选方案及落选理由（如有方案比选） |
| `## Socratic Review` | 推荐 | 自问自答式审查 |

Release spec 的 design 结构不同，应包含以下内容：

| Section | 必要性 | 说明 |
|---------|--------|------|
| 技术摘要汇总 | 必须 | 从各 feature spec 的 design.md 提取关键信息，每个 feature 附 spec 和 proposal 引用 |
| Issue 修复方案 | 必须 | 针对 requirements 中确认需修复的 issue，给出技术分析和修复思路 |
| 测试策略 | 必须 | 自动化测试覆盖情况 + 手工测试范围 |
| 收敛计划 | 必须 | 文档收敛、版本号更新、归档步骤 |

### 检查项

- [ ] 一级标题存在
- [ ] 技术方案主体存在，且承接了 requirements 中的需求
- [ ] 接口签名 / 数据模型有明确定义（不是模糊描述）
- [ ] 各 section 之间使用 `---` 分隔

---

## 3. Requirements 覆盖校验

逐条检查 requirements.md 中的 requirement，确认 design 中有对应的技术方案：

- [ ] 每条 requirement 在 design 中都有对应的实现描述
- [ ] 无遗漏的 requirement（特别注意错误处理、边界条件相关的 requirement）
- [ ] design 中的方案不超出 requirements 的范围（不做 requirements 未要求的事）

---

## 4. Impact Analysis 校验

Impact Analysis 必须至少覆盖以下维度：

- [ ] 受影响的 state 文档条目（具体文件名及 section）
- [ ] 如果 `graphify_ready`，是否利用 graphify 查询结果辅助识别了受影响范围（如遗漏的下游依赖、跨 community 的连锁影响）
- [ ] 现有 model / service / CLI 行为的变化
- [ ] 是否涉及数据模型变更——如涉及，是否提醒了旧数据兼容
- [ ] 是否涉及外部系统交互变化
- [ ] 是否涉及配置项变更（新增、删除、默认值变化）

如果 Impact Analysis 缺少上述任一维度且该维度与当前 feature 相关，补充之。如果某维度确实不适用，可标注"不涉及"。

---

## 5. 技术方案质量校验

- [ ] 技术选型有明确理由（不是无理由地选择某个库或模式）
- [ ] 接口签名足够清晰，能让 task 独立执行（参数类型、返回类型、异常类型）
- [ ] 模块间依赖关系清晰，无循环依赖（如果 `graphify_ready`，对照 graphify 查询结果验证）
- [ ] 无过度设计（当前不需要的抽象、预留的扩展点）
- [ ] 与 state 文档中描述的现有架构一致（不引入矛盾的设计）

---

## 6. Socratic Review 校验

如果文档缺少 `## Socratic Review` section，gatekeeper 应补充一个轻量版，至少覆盖：

- design 是否完整覆盖了 requirements 中的每条需求？有无遗漏？
- 技术选型是否合理？是否有更简单或更成熟的替代方案？
- 接口签名和数据模型是否足够清晰，能让 task 独立执行？
- 模块间的依赖关系是否会引入循环依赖或过度耦合？
- 是否有过度设计的部分？（当前不需要的抽象、预留的扩展点等）
- 是否存在未经确认的重大技术选型？如果 design 中没有 Alternatives Considered，是否确实只有一种合理选择？
- Impact Analysis 是否充分？（受影响的 state 条目、行为变化、数据模型变更、外部系统交互、配置项变更）

如果已有 Socratic Review，检查其覆盖度是否充分，不充分则补充。

---

## 7. 目的性审查

完成逐项校验后，退后一步，审视文档整体是否达到了 design 阶段的目的。

Design 的核心目的是：**让读者（包括后续 tasks 阶段的 agent）清楚地知道用什么技术方案实现 requirements，以及方案的关键决策和边界**。

### 审查清单

- [ ] **Requirements CR 回应**：读取 requirements.md Gatekeep Log 中的 Clarification Round，检查用户已回答的每个决策是否在 design 中得到体现。未体现的决策应补充到对应的技术方案中。
- [ ] **技术选型明确**：文档是否对所有关键技术选型给出了明确结论和理由？是否存在"待定"或含糊的选型（如"可以用 A 或 B"但未做决定）？
- [ ] **接口定义可执行**：接口签名、数据模型是否足够具体，能让 task 执行者直接编码？是否存在参数类型、返回类型、异常类型不明确的接口？
- [ ] **Requirements 全覆盖**：每条 requirement 是否都有对应的技术方案？是否有 requirement 被遗漏或只是泛泛提及而无具体实现路径？
- [ ] **Impact 充分评估**：影响分析是否覆盖了所有受影响的模块、数据模型、配置项和外部系统？是否有潜在影响被遗漏？
- [ ] **可 task 化**：仅凭这份 design，tasks 阶段的 agent 是否能拆出可独立执行的 task？是否存在模块间关系不清、执行顺序不明的问题？

如果发现文档在上述任一维度不达标，直接修正。修正后在 Gatekeep Log 中记录修正项（修正类型为 `目的`）。

---

## 8. Gatekeep Log

将校验过程中的修正项写入 design.md 末尾的 `## Gatekeep Log` section。

---

## 9. Clarification Round (CR)

校验和修正全部完成后，生成面向 tasks 阶段的 CR 问题。

阅读 design.md、requirements.md、goal.md（如存在）及相关 SSOT，提出 **3 个以上**的澄清问题。

CR 聚焦 **design 到 tasks 的衔接**——design 中哪些技术决策在拆分为具体 task 时可能存在歧义，需要用户在进入 tasks 前做出决策。**不应**问 design 自身产出物的格式问题（接口签名写法、section 结构等），那些已在校验阶段处理。也**不应**重复 goal.md 或 requirements Gatekeep Log 中已澄清过的问题。

聚焦方向：
- 实现顺序是否有偏好？（如先做核心路径还是先做基础设施）
- 测试策略是否需要明确？（哪些用单元测试、哪些用集成测试、哪些需要手工测试）
- 是否有跨模块的依赖需要协调？（如 A 模块的接口变更影响 B 模块的实现顺序）
- design 中的某个技术方案是否有多种等价的拆分方式？（如一个大改动是拆成按模块的 task 还是按功能切片的 task）
- 是否有 design 中标注为"可选"或"推荐"但未最终确定的技术细节？（如具体的数据结构选择、缓存策略等）

**不应涉及的方向**（这些属于 gk-requirements CR 的范畴）：
- 行为层面的 trade-off（如兼容性 vs 清晰度）——这是 requirements 阶段的决策
- 非功能约束的有无（如是否需要幂等性）——这是 requirements 阶段的决策
- 并发模型、错误恢复策略的选择——如果 requirements CR 已澄清了约束，design CR 不应重复；如果 design 中已做出选型，不需要再问用户确认

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

## 10. Completion

Gatekeeper 完成所有校验、修正和 CR 生成后，向 main-agent 返回以下内容：

1. **校验结果摘要**：通过 / 已修正后通过，列出修正项（如有）
2. **CR 待确认**：告知 main-agent design.md 的 Gatekeep Log 中有待用户回答的 CR 问题

Main-agent 收到后：
1. 将校验结果告知用户
2. 读取 design.md 中 Gatekeep Log 的 `### Clarification Round` 小节
3. **逐题**与用户交互——每次只问一个问题，使用 `userInput` 工具提问，等回答后再问下一个
4. 将用户回答写入对应的 `**A:**` 行
5. 所有问题回答完毕后，将 `**状态**` 更新为 `已完成`
