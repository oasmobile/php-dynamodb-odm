---
inclusion: auto
description: 当执行 spec task（编码、测试、code review）时读取，包含执行模型、并行策略、commit 规则、测试分层、bug fix 规则、手工测试、code review 规范
---

# Spec Execution

本文件定义 tasks.md 的执行规范。

---

## Task 执行模型

- 当前 spec 目录下的 `tasks.md` 为唯一执行清单
- top-level task 必须按序号逐项完成，不允许跨步跳跃
- 每个 task 通过独立 session 执行（不可假设上下文继承）
- checkpoint 成功时，进行一次 commit

### Pre-execution Review

每次开始执行 tasks.md（或从中断处恢复执行）时，先做一次轻量预检：

1. **Drift 检测**：将当前要执行的 task 涉及的关键文件与 design.md 中的预期做快速比对——如果代码结构、接口签名、依赖关系已发生变化（例如被其他分支 merge 改动），标记为 drift
2. **决策**：
   - 无 drift → 正常执行
   - 发现 drift 但不影响当前 task 的实现路径 → 记录 drift，正常执行
   - 发现 drift 且影响当前 task 的实现路径 → **停下来**，向用户报告 drift 内容，等待确认后再继续

### 并行执行策略

执行一个 top-level task 时，先分析其所有 sub-task：

1. **上下文分析**：列出每个 sub-task 需要读取的文件和需要修改的文件
2. **冲突检测**：如果两个 sub-task 修改同一个文件，或一个 sub-task 的输出是另一个的输入，则存在冲突
3. **分组**：将不冲突的 sub-task 分为可并行组，冲突的 sub-task 保持串行
4. **并行执行**：同一组内的 sub-task 使用并行的 sub-agent 同时执行
5. **汇总**：并行组内所有 sub-task 完成后，统一 commit 一次，然后进入下一组

如果 tasks.md 中已标注了并行计划（如 `[并行: 1.1, 1.2, 1.3]`），优先遵循标注；否则按上述策略自行判断。

### Checkpoint 执行

- checkpoint task 必须执行其描述中指定的验证命令
- 通过标准：不仅要求测试全部通过，还要求**输出干净**——无 compiler warning、无 deprecation warning、无异常堆栈、无非预期的 stderr 输出
- 未通过 checkpoint，不得进入下一层实现
- checkpoint 失败时，修复问题后重新执行验证，直到通过

---

## 自动化测试分层

| 层 | 测试类型 | 覆盖范围 |
|----|---------|---------|
| Service | property-based tests | 数据生成、唯一性、不变性、边界条件 |
| API / CLI / Controller | integration tests | I/O 行为（request/response、stdout/stderr、exit code） |
| 单元测试 | 补充测试 | 错误路径与边界条件 |

### 单元测试覆盖自检

每次完成单元测试编写后，对照 requirements 和当前实现，review 是否覆盖了以下场景：

- 正常流程（happy path）
- 边界条件（空值、空列表、极端输入）
- 错误场景（无效输入、不存在的资源、异常路径）
- 中文 / Unicode 内容的 round-trip

如果发现遗漏场景，补充测试后再标记 task 完成。

### Bug Fix 测试规则

| 场景 | 测试要求 | 强度 |
|------|---------|------|
| 正式 bug fix（有 issue 记录） | 必须先编写 reproduction test，**运行测试确认在未修复代码上失败**（确认失败原因是 bug 本身而非测试有误），然后修复代码，再次运行测试确认通过 | must |
| 开发中发现的行为问题（逻辑错误、边界遗漏、状态异常等） | 应补充对应的 test case 覆盖该场景，不强制 test-first 顺序 | should |
| 开发中的编码错误（编译失败、typo、配置缺失等） | 不需要专门写 test | — |

判定标准：如果问题属于"行为层面"（即系统产出了错误的结果或行为），就应该有 test 覆盖；如果只是"写错了"（编译不过、拼写错误），则不需要。

---

## 手工测试任务

- 执行时机：feature 分支上完成（finish 之前），或推迟到 release stabilize 阶段
- 生成或执行手工测试时，按 manual-testing 规范编排和执行

---

## Finish 前 Code Review Task

任何 spec（feature / release / hotfix），在 finish 之前，`tasks.md` 必须包含一个 code-review task。

执行时委托给 `code-reviewer` sub-agent，不在主 agent 上下文中内联执行。

---

## Release Stabilize 特殊规则

在 release 分支上执行 stabilize 阶段的测试 task 时，遵循以下额外规则：

### Alpha Tag

- 每个测试 task 开始前打 alpha tag（如 `v0.2-alpha3`）
- alpha tag 序号：查询已有 alpha tag，取最大序号 +1；无 alpha tag 则为 alpha1
- alpha tag 打出后到 commit 前，禁止任何 git commit

### 问题处理

- 发现问题时：3 轮对话内能修复则直接修复，不提 issue
- 超过 3 轮修不好的：创建 issue 文件，标注发现时的 alpha tag

### Issue Severity 处理

| Severity | 处理方式 |
|----------|----------|
| `[P0] critical` | 必须在当前 release 分支上修复，阻塞 finish |
| `[P1] major` | 必须在当前 release 分支上修复，阻塞 finish |
| `[P2] minor` | 需用户确认是否可接受带 issue 发布 |
| `[P3] trivial` | 可忽略，不阻塞发布 |

### Issue 修复规则

- 修复前必须先编写 reproduction test
- 修复后重新执行对应测试项，确认通过后更新 issue 状态为 closed

### Beta Tag

- beta tag 由用户手动控制，agent 不可自主打

---

## Blocker Escalation

遇到以下任一情况时，**必须立即停止执行并向用户报告**，不得自行绕过或硬冲：

| 场景 | 说明 |
|------|------|
| 修复失败 | 同一问题尝试 3 轮仍无法解决（编译错误、测试失败、逻辑缺陷） |
| 指令不清 | task 描述模糊，存在多种合理理解，不同理解会导致不同实现 |
| 缺少依赖 | task 依赖的外部资源、配置、权限不可用，且无法在当前 session 内解决 |
| Design drift | Pre-execution Review 发现代码现状与 design 不一致，且影响实现路径 |
| 设计决策 | 实现过程中遇到 design.md 未覆盖的架构、接口、数据模型决策 |

报告时应包含：问题描述、已尝试的方案（如有）、建议的下一步选项。

---

## Error Handling

以下为常规技术错误的处理方式（未触发 Blocker Escalation 时）：

- 编译失败：分析错误信息，修复后重新编译
- 测试失败：分析失败原因，修复代码或测试后重新运行
- Git 操作失败：输出错误信息，不要静默忽略
