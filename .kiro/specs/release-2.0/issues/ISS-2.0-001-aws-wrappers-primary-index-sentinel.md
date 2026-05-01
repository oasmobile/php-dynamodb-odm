# ISS-2.0-001 `DynamoDbIndex::PRIMARY_INDEX` (bool `true`) 作为 `$indexName` 参数的 sentinel value 在 PHP 8 严格类型下易导致隐式类型转换 bug

| 字段 | 值 |
|------|-----|
| Severity | [P2] minor |
| Status | open |
| Found In | v2.0.0-alpha.0 |
| Fixed In | |
| Related Test | tasks.md 10.6 |

## Description

`oasis/aws-wrappers` v3.0.1 中，`DynamoDbIndex::PRIMARY_INDEX` 定义为 `const PRIMARY_INDEX = true;`（bool 类型），用作 scan/query 系列方法的 `$indexName` 参数的 sentinel value，表示"使用主索引，不传 IndexName 给 AWS SDK"。

`ScanAsyncCommandWrapper` 中通过 `$indexName !== DynamoDbIndex::PRIMARY_INDEX` 判断是否设置 `IndexName`，逻辑本身正确。但当调用方（如 `ItemRepository`）的参数类型声明为 `string $indexName`（而非 `string|bool`）时，PHP 8 的严格类型会将 `true` 隐式转换为 `"1"`，导致 `"1" !== true` 为 `true`，`IndexName = "1"` 被传给 AWS SDK，触发验证错误：`[IndexName] expected string length to be >= 3, but found string length of 1`。

虽然直接原因是调用方类型声明不当（本项目已修复），但 `oasis/aws-wrappers` 使用 bool `true` 作为 string 参数的 sentinel value 是一个设计隐患，建议改用字符串常量（如 `const PRIMARY_INDEX = '__primary__'`）或 null。

## Steps to Reproduce

1. 环境：PHP 8.5、oasis/aws-wrappers v3.0.1
2. 定义一个方法，参数类型为 `string $indexName = DynamoDbIndex::PRIMARY_INDEX`
3. 调用该方法时不传 `$indexName`，默认值 `true` 被隐式转为 `"1"`
4. 传递给 `ScanAsyncCommandWrapper`，`"1" !== true` 为 `true`，设置 `IndexName = "1"`
5. AWS SDK 验证失败

## Expected Behavior

`DynamoDbIndex::PRIMARY_INDEX` 作为 sentinel value 应该在整个调用链中保持语义一致，不受 PHP 严格类型转换影响。

## Actual Behavior

当调用方参数类型声明为 `string`（而非 `string|bool`）时，`true` 被隐式转为 `"1"`，sentinel 语义丢失，导致 AWS SDK 验证错误。

## Analysis

根本原因是使用 bool `true` 作为 string 类型参数的 sentinel value。在 PHP 8 严格类型环境下，`string|bool` union type 的参数如果在调用链中某一层被收窄为 `string`，bool sentinel 就会被隐式转换。建议将 `PRIMARY_INDEX` 改为字符串常量或 null，避免跨类型 sentinel。

## History

- `2026-05-01` `v2.0.0-alpha.0` [发现] release/2.0 stabilize 阶段集成测试发现 `removeAll()` 和 `scanCount()` 调用失败
