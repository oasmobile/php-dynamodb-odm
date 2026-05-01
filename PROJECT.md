# Project: oasis/dynamodb-odm

DynamoDB ODM（Object Data Mapping）库，为 AWS DynamoDB 提供类似 Doctrine ORM 的对象映射能力。

---

## 技术栈

| 项 | 值 |
|----|-----|
| 语言 | PHP ≥ 7.1 |
| 包管理 | Composer |
| 命名空间 | `Oasis\Mlib\ODM\Dynamodb` |
| 注解引擎 | `doctrine/annotations` ^1.4 |
| 控制台 | `symfony/console` ^4.4 |
| AWS 封装 | `oasis/aws-wrappers` ^2.10 |
| 测试框架 | PHPUnit ^5.7 |
| 许可证 | MIT |

---

## 构建与测试命令

```bash
# 安装依赖
composer install

# 运行单元测试
./vendor/bin/phpunit

# Schema CLI 工具
./vendor/bin/oasis-dynamodb-odm odm:schema-tool:create
./vendor/bin/oasis-dynamodb-odm odm:schema-tool:update
./vendor/bin/oasis-dynamodb-odm odm:schema-tool:drop
```

---

## 关键入口

| 入口 | 路径 |
|------|------|
| 核心管理器 | `src/ItemManager.php` |
| CLI 入口 | `bin/oasis-dynamodb-odm` |
| CLI 配置 | `odm-config.php` |
| 测试引导 | `ut/bootstrap.php` |
| PHPUnit 配置 | `phpunit.xml` |

---

## 版本号位置

- `composer.json` → `version` 字段（当前未显式声明，由 Packagist / tag 管理）

---

## 敏感文件

- `odm-config.php` — 包含 AWS 配置引用
- `ut/ut.yml` — 测试用 DynamoDB 连接配置（由 `ut/tpl.ut.yml` 模板生成）
- `ut/cache/` — 注解缓存目录（已 gitignore）
