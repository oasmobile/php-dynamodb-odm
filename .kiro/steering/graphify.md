---
inclusion: auto
description: 涉及 graphify 用法的时候读取
---

# graphify — 项目知识图谱

本项目维护了一份持久化知识图谱，存放在 `graphify-out/`。图谱将代码、文档、proposal、spec 等所有文件的实体和关系提取为节点与边，支持跨文件、跨模块的结构化查询。

---

## 什么时候该用图谱

以下场景 **优先查图谱**，而不是 grep 或逐文件阅读：

| 场景 | 图谱能做什么 | 怎么做 |
|------|-------------|--------|
| 理解架构全貌 | 看 god nodes（核心抽象）和社区结构（模块边界） | 读 `graphify-out/GRAPH_REPORT.md` 的 God Nodes 和 Communities 章节 |
| 评估变更影响面 | 找到某个类/概念的所有直接和间接依赖 | `/graphify query "what depends on MicroKernel"` |
| 追踪跨模块关系 | 找两个概念之间的最短路径，发现隐藏耦合 | `/graphify path "MicroKernel" "CrossOriginResourceSharingProvider"` |
| 理解不熟悉的模块 | 获取某个节点的上下文解释（邻居、所属社区、边关系） | `/graphify explain "ConfigurationValidationTrait"` |
| 确定测试优先级 | god nodes 和 cross-community bridges 是变更风险最高的点 | 读 GRAPH_REPORT.md 的 God Nodes 和 Surprising Connections |
| 新 session 快速恢复上下文 | 不用重读几百个文件，读报告即可理解项目结构 | 读 `graphify-out/GRAPH_REPORT.md` |

## 什么时候不需要图谱

- 已经明确知道要改哪个文件、改什么内容 → 直接读文件
- 简单的文本搜索（找某个字符串在哪出现） → grep 更快
- 图谱尚未更新到最新代码 → 先 `/graphify update` 再查询

---

## 图谱输出文件

| 文件 | 内容 | 用途 |
|------|------|------|
| `graphify-out/GRAPH_REPORT.md` | God nodes、社区结构、cohesion 分数、surprising connections、建议问题 | **首选入口**——回答架构问题前先读这个 |
| `graphify-out/graph.json` | 完整的节点和边数据（NetworkX JSON 格式） | 程序化查询、`/graphify query` 的数据源 |
| `graphify-out/graph.html` | 交互式可视化 | 浏览器打开，直观探索社区和连接 |
| `graphify-out/cost.json` | 累计 token 消耗 | 追踪图谱构建成本 |

---

## 关键概念速查

- **God Node**：边数最多的核心节点，变更影响面最大。当前项目的 god nodes 包括 `FrameworkConfig`、`MicroKernel`、`SilexKernelTest` 等
- **Community**：图谱自动检测的内聚模块。同一社区内的节点关系紧密，跨社区的边代表模块间耦合
- **Cohesion Score**：社区内部连接密度，越高越内聚。低 cohesion 的社区可能是职责不清的信号
- **Surprising Connection**：跨社区的意外连接——你可能没意识到的耦合点
- **Cross-community Bridge**：连接不同社区的桥梁节点（betweenness 高），是跨模块交互的关键路径
- **Hyperedge**：3+ 个节点共享一个概念/流程的超边，捕捉 pairwise 边无法表达的多方关系

---

## 维护节奏

- 每个 Phase / feature 完成后运行 `/graphify update`，保持图谱与代码同步
- 图谱演变本身是验证手段——比如 Phase 1 完成后 `SilexKernel` 相关节点应消失，`MicroKernel` 连接模式应变化
