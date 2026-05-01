---
inclusion: manual
description: Spec gatekeeper 校验 tasks 阶段的详细指引。由 spec-gatekeeper 在校验 tasks.md 时读取。
---

# Tasks Gatekeep 指引

本文件定义 tasks.md 的校验标准。Gatekeeper 按以下清单逐项检查，发现问题直接修正。

---

## 执行顺序

1. 机械扫描
2. 结构校验
3. Task 格式校验
4. Requirement 追溯校验
5. 依赖与排序校验
6. Graphify 跨模块依赖校验（如 `graphify_ready`）
7. Checkpoint 校验
8. Test-first 校验
9. Task 粒度校验
10. 手工测试 Task 校验
11. Code Review Task 校验
12. 执行注意事项校验
13. Socratic Review 校验
14. 目的性审查
15. 将修正项写入 Gatekeep Log
16. Completion：向 main-agent 返回结果

---

## 1. 机械扫描

在所有语义校验之前，先执行机械扫描：

- [ ] 无 TBD / TODO / 待定 / 占位符
- [ ] 无空 section 或不完整的列表
- [ ] 内部引用一致（requirement 编号、design 中的模块名）
- [ ] checkbox 语法正确（`- [ ]` 而非 `- []` 或其他变体）
- [ ] 无 markdown 格式错误

发现问题直接修正，不需要在 Gatekeep Log 中逐一列出机械扫描的修正。

---

## 2. 结构校验

Feature / Hotfix 的 tasks.md 必须包含 `## Tasks` section，其中的 top-level task 遵循固定顺序：

| 序号 | 类型 | 说明 |
|------|------|------|
| 1 ~ N | 自动化实现 task | 代码实现、自动化测试等 |
| N+1 | 手工测试 task | 整个 feature 统一一个手工测试 top-level task |
| 最后一个 | Code Review task | finish 前的统一 code review |

Release spec 的 tasks 结构不同：

- 结构为 task → sub-task → test item 三级嵌套
- 手工测试类 top-level task 的第一个 sub-task 为 "Increment alpha tag"
- 测试项使用 checkbox 语法：`- [ ]` 未测试、`- [x]` 通过、`- [-]` 进行中
- 明确前置条件（构建命令、环境变量等）
- 文件包含两个主要 section：
  - `## Tasks`：功能验证测试清单
  - `## Issues`：stabilize 阶段新发现的 issue（初始为空）
- 最后一个顶层 task 为 Code Review

### 检查项

- [ ] `## Tasks` section 存在
- [ ] Release spec 中手工测试类 top-level task 的第一个 sub-task 是 "Increment alpha tag"
- [ ] 倒数第一个 top-level task 是 Code Review
- [ ] 倒数第二个 top-level task 是手工测试（feature / hotfix spec）
- [ ] 自动化实现 task 排在手工测试和 Code Review 之前

---

## 3. Task 格式校验

每个 task 必须使用 checkbox 语法：

```markdown
- [ ] N. Top-level task 名称
  - [ ] N.1 Sub-task 描述（Ref: Requirement X, AC Y）
  - [ ] N.2 Sub-task 描述
  - [ ] N.3 Checkpoint: 验证描述
```

### 检查项

- [ ] 所有 task 使用 `- [ ]` checkbox 语法
- [ ] top-level task 有序号（1, 2, 3...）
- [ ] sub-task 有层级序号（1.1, 1.2, 1.3...）
- [ ] 序号连续，无跳号

---

## 4. Requirement 追溯校验

- [ ] 每个实现类 sub-task 引用了具体的 requirements 条款（`Ref: Requirement X, AC Y` 格式）
- [ ] requirements.md 中的每条 requirement 至少被一个 task 引用（无遗漏的 requirement）
- [ ] 引用的 requirement 编号和 AC 编号在 requirements.md 中确实存在（无悬空引用）

> **注意**：Checkpoint、手工测试、Code Review 类 task 不要求引用 requirement。

---

## 5. 依赖与排序校验

- [ ] top-level task 按依赖关系排序（被依赖的 task 排在前面）
- [ ] 无循环依赖
- [ ] 如果标注了并行计划（如 `[并行: 1.1, 1.2, 1.3]`），检查并行条件是否成立：各 sub-task 不修改同一个文件且不存在调用依赖

---

## 6. Graphify 跨模块依赖校验

> 仅在 `graphify_ready` 时执行，否则跳过本步骤。

利用 graphify 子命令验证 task 排序是否遗漏了隐含的跨模块依赖：

1. 识别 tasks 涉及的核心模块（从 task 描述中提取类名 / 文件名）
2. 对每个核心模块执行 `graphify query "what depends on <Module>"` 查询其上下游依赖
3. 对存在疑似依赖的模块对执行 `graphify path "A" "B"` 确认依赖路径
4. 对照查询结果，检查 task 排序是否遗漏了隐含的跨模块依赖（如 A 模块的变更会影响 B 模块，但 B 的 task 排在 A 之前）

### 检查项

- [ ] 已对核心模块执行 graphify 依赖查询
- [ ] task 排序与 graphify 揭示的模块依赖一致，无遗漏的隐含跨模块依赖
- [ ] 如发现遗漏的依赖，已调整 task 排序或在 task 描述中补充依赖说明

---

## 7. Checkpoint 校验

- [ ] checkpoint 不作为独立的 top-level task，而是作为每个 top-level task 的最后一个 sub-task
- [ ] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [ ] checkpoint 描述中包含具体的验证命令或验证方式（如"执行全量测试确认通过"）以及 commit 动作
- [ ] checkpoint 不是空泛的"确认完成"，而是有可执行的验证步骤和明确的 commit

---

## 8. Test-first 校验

对于新增行为的实现 sub-task，检查是否遵循 test-first 编排：

- 推荐顺序：① 编写测试 → ② 运行测试确认失败（确认失败原因是功能缺失而非测试本身有误） → ③ 编写实现 → ④ 运行测试确认通过
- 如果一个 sub-task 同时包含实现和测试，描述中应明确 test-first 顺序

> **注意**：这是推荐而非强制。如果 task 描述中已包含测试要求但未严格按 test-first 排列，不视为错误，但可在 Gatekeep Log 中建议优化。

---

## 9. Task 粒度校验

- [ ] 每个 sub-task 足够具体，可以在独立 session 中执行（不依赖上下文继承）
- [ ] 无过粗的 task（一个 sub-task 包含多个不相关的实现）
- [ ] 无过细的 task（琐碎到不值得单独列出的操作）
- [ ] 所有 task 均为 mandatory（不存在 optional task）

---

## 10. 手工测试 Task 校验

- [ ] 手工测试 top-level task 存在
- [ ] 手工测试覆盖了 requirements 中的关键用户场景
- [ ] 手工测试场景描述具体，可执行

---

## 11. Code Review Task 校验

- [ ] Code Review 是最后一个 top-level task
- [ ] 描述为"委托给 code-reviewer sub-agent 执行"或等效表述
- [ ] **不应**在 task 描述中展开 review checklist 或 fix policy（这些由 code-reviewer agent 自身定义）

---

## 12. 执行注意事项校验

tasks.md 必须包含 `## Notes` section（位于 `## Tasks` 之后），提醒执行者在执行 task 时应遵循的关键规范。

### 检查项

- [ ] `## Notes` section 存在
- [ ] 明确提到执行时须遵循 `spec-execution.md`（或等效表述，如"按 spec-execution 规范执行"）
- [ ] 明确说明 commit 随 checkpoint 一起执行（或等效表述，如"checkpoint 中已包含 commit"）
- [ ] 包含当前 spec 特有的执行要点（如特殊的构建命令、环境前置条件、数据兼容注意事项等）——如果 design 或 requirements 中没有特殊要点，至少保留对 `spec-execution.md` 的引用和 commit 时机说明即可

> **注意**：如果文档使用了 `## Execution Notes` 等非标准名称但内容等价，gatekeeper 应将其重命名为 `## Notes` 并补充缺失内容，而非另建一个 section。

---

## 13. Socratic Review 校验

如果文档缺少 `## Socratic Review` section，gatekeeper 应补充一个轻量版，至少覆盖：

- tasks 是否完整覆盖了 design 中的所有实现项？有无遗漏的模块或接口？
- task 之间的依赖顺序是否正确？是否存在隐含的前置依赖未体现在排序中？
- 每个 task 的粒度是否合适？是否有过粗或过细的 task？
- checkpoint 的设置是否覆盖了关键阶段？
- 标注为可并行的 sub-task 是否真的满足并行条件？
- 手工测试是否覆盖了 requirements 中的关键用户场景？

如果已有 Socratic Review，检查其覆盖度是否充分，不充分则补充。

---

## 14. 目的性审查

完成逐项校验后，退后一步，审视文档整体是否达到了 tasks 阶段的目的。

Tasks 的核心目的是：**提供一份可直接执行的实现计划，让执行者（agent 或开发者）无需回溯 design 即可逐条完成所有实现工作**。

### 审查清单

- [ ] **Design CR 回应**：读取 design.md Gatekeep Log 中的 Clarification Round，检查用户已回答的每个决策（如实现顺序偏好、测试策略、task 拆分方式等）是否在 tasks 编排中得到体现。未体现的决策应调整对应的 task 排序或描述。
- [ ] **Design 全覆盖**：tasks 整体是否覆盖了 design 中的所有模块、接口和实现项？是否有 design 中明确要做的事在 tasks 中找不到对应 task？
- [ ] **可独立执行**：每个 sub-task 的描述是否足够自包含？执行者是否能仅凭 task 描述（加上 Ref 指向的 requirement 和 design section）完成实现，而不需要猜测上下文？
- [ ] **验收闭环**：checkpoint + 手工测试 + code review 三者组合起来，是否构成了完整的验收闭环？如果所有 checkpoint 通过、手工测试通过、code review 通过，feature 是否就可以交付？
- [ ] **执行路径无歧义**：task 的排序和依赖关系是否清晰到执行者不需要自行判断"先做哪个"？是否存在两个 task 看起来都可以先做但实际有隐含依赖的情况？

如果发现文档在上述任一维度不达标，直接修正。修正后在 Gatekeep Log 中记录修正项（修正类型为 `目的`）。

---

## 15. Gatekeep Log

将校验过程中的修正项写入 tasks.md 末尾的 `## Gatekeep Log` section。

---

## 16. Completion

Gatekeeper 完成所有校验和修正后，向 main-agent 返回以下内容：

1. **校验结果摘要**：通过 / 已修正后通过，列出修正项（如有）
2. **下一步建议**：tasks 校验完成后，spec 三阶段校验全部结束，可以开始执行 task
