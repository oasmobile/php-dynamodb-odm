---
inclusion: auto
description: 当执行 shell 命令时读取，包含禁止危险命令和需确认操作的安全约束
---

# Command Safety Rules

Agent 执行 shell 命令时的安全约束。

---

## 禁止直接执行的命令

以下命令模式禁止 agent 自主执行：

- `rm -rf` — 递归强制删除
- `git rebase` — 改写历史（严格禁止）
- `git push --force` — 强制推送（覆盖远程历史）
- `git reset --hard` — 硬重置（丢弃未提交变更）
- `git checkout -- <path>` — 撤销工作区修改（不可恢复，必须经用户明确同意）
- `curl | bash`（及等价的 `wget | sh`）— 远程脚本盲执行
- `chmod 777` — 开放全部权限

---

## 需要用户确认的流程

如果操作确实需要上述命令：

1. 向用户解释为什么需要执行该命令
2. 明确说明影响范围（哪些文件 / 分支 / 权限会被改变）
3. 等待用户明确确认后再执行
