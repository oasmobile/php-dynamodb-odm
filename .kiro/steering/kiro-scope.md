---
inclusion: auto
description: Kiro 平台专属规则，始终生效
---

# Kiro Scope Rules

## `<spec-dir>` 取值

- `<spec-dir>` = `.kiro/specs`

## 项目信息来源

- Agent 首次接触项目时，必须先读取根目录下的 `PROJECT.md` 获取项目的技术栈、构建命令、运行入口、敏感文件清单等项目特定信息。

## 禁止读取 `.cursor` 目录

- 不要读取、引用或参考 `.cursor/` 目录下的任何文件（包括 `.cursor/rules/` 中的 rule 文件）。
- `.cursor/` 是另一个编辑器的配置目录，与 Kiro 无关。Kiro 的 steering 规则仅来自 `.kiro/steering/`。
