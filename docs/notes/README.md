# Notes

轻量意图暂存，存放未成熟为 proposal 的零散想法、观察和改进方向。

---

## 定位

回答：想做什么（lightweight intent）。

介于日常观察与正式 proposal 之间，用于捕捉跨 feature 的灵感和改进方向。

---

## 规则

- 每条 note 一个 markdown 文件，命名使用关键词（如 `feishu-batch-api.md`）
- 不要求编号体系，不要求状态流转
- 分支规则：在 develop 上创建和维护，feature 分支中也可写入
- 消费后处理及完整生命周期规则见 `.kiro/steering/doc-lifecycle.md` Notes 生命周期章节

---

## 文件结构

```markdown
# <标题>

> 来源：feature/<来源分支> | 日常观察 | ...

<正文>
```

---

## 不应包含

- 已结构化的需求（属于 proposal）
- 已知缺陷或技术债（属于 issue）
- 实现计划（属于 spec）
