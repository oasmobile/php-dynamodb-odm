# Architecture

系统架构与工程约束的 SSOT。

---

## 分层结构

```
Attributes           Attribute 定义层：Field / Index / Item / PartitionedHashKey（PHP 8 原生 #[Attribute]）
ItemReflection       元数据解析层：通过 ReflectionAPI 将 Attribute 解析为运行时反射信息
ItemManager          核心管理器：对象生命周期管理入口
ItemRepository       仓库层：CRUD / Query / Scan 操作
ManagedItemState     状态追踪层：脏检查、CAS、PartitionedHashKey 更新
DBAL/Drivers         数据库抽象层：Connection 接口 + DynamoDbConnection 实现
DBAL/Schema          Schema 工具层：表的创建 / 更新 / 删除
Console/Commands     CLI 命令层：schema-tool 系列命令
Exceptions           异常层
```

---

## 核心类职责

| 类 | 职责 |
|----|------|
| `ItemManager` | 统一入口；管理命名空间扫描、Repository 缓存；提供 `persist` / `remove` / `flush` / `get` / `detach` / `clear` / `refresh` 等操作 |
| `ItemReflection` | 通过 `ReflectionClass::getAttributes()` / `ReflectionProperty::getAttributes()` 解析 `#[Item]` / `#[Field]` / `#[PartitionedHashKey]` Attribute；提供 hydrate / dehydrate、属性读写、主键提取、字段映射 |
| `ItemRepository` | 每个 Item 类对应一个实例；负责 get / query / scan / batchGet / persist / remove / flush 等数据操作；内部维护 `ManagedItemState` 映射 |
| `ManagedItemState` | 追踪单个对象的状态（NEW / MANAGED / REMOVED）；脏数据检测；CAS 时间戳更新；PartitionedHashKey 计算 |
| `Connection` | 数据库连接接口；定义所有底层数据操作方法签名 |
| `DynamoDbConnection` | `Connection` 的 DynamoDB 实现；委托 `oasis/aws-wrappers` 的 `DynamoDbTable` |
| `AbstractSchemaTool` / `DynamoDbSchemaTool` | Schema 管理；创建 / 更新 / 删除 DynamoDB 表及 GSI |

---

## 对象生命周期

```
new → persist() → [STATE_NEW]
                      ↓ flush()
                  [STATE_MANAGED] ←→ 修改属性（脏检查）
                      ↓ remove()
                  [STATE_REMOVED]
                      ↓ flush()
                  从 itemManaged 中移除
```

- `flush()` 是唯一触发数据库写操作的方法
- `detach()` 使对象脱离管理，后续修改不再同步
- `clear()` 清空所有托管对象

---

## Attribute 体系

| Attribute | 目标 | 作用 |
|-----------|------|------|
| `#[Item]` | 类 | 声明 DynamoDB 表映射：表名、主键索引、GSI、LSI、repository 类、是否 projected |
| `#[Field]` | 属性 | 声明属性到 DynamoDB attribute 的映射：类型、别名、CAS 策略、是否 GSI key |
| `Index` | 嵌套 | 描述索引结构：hash key、range key、索引名（作为 `#[Item]` 的嵌套参数使用） |
| `#[PartitionedHashKey]` | 属性 | 声明分区哈希键：baseField、hashField、分区大小 |

Attribute 类位于 `src/Attributes/` 目录，命名空间为 `Oasis\Mlib\ODM\Dynamodb\Attributes`。

---

## DBAL 抽象

- `Connection` 接口定义了所有数据操作（get / set / query / scan / batch 等）
- `AbstractDbConnection` 提供公共属性管理（tableName / attributeTypes / itemReflection）
- `DynamoDbConnection` 委托 `DynamoDbTable`（来自 `oasis/aws-wrappers`）执行实际操作
- `ItemManager` 构造时接受两个参数：`$dbCnn`（`Connection` 实例或 AWS 配置数组）和 `$defaultTablePrefix`（表名前缀）；传入数组时默认创建 `DynamoDbConnection`

---

## CAS（Check-and-Set）机制

- `Field::cas` 支持三种模式：`disabled` / `enabled` / `timestamp`
- `timestamp` 模式在每次 flush 时自动更新为当前时间戳
- CAS 检查在 `flush()` 时执行；冲突抛出 `DataConsistencyException`
- `ItemManager::setSkipCheckAndSet(true)` 可全局跳过 CAS，此时使用 batchPut 批量写入

---

## CLI 工具

通过 `bin/oasis-dynamodb-odm` 入口，注册于 `ConsoleHelper`：

| 命令 | 功能 |
|------|------|
| `odm:schema-tool:create` | 创建所有托管 Item 对应的表（支持 `--skip-existing-table`、`--dry-run`） |
| `odm:schema-tool:update` | 创建缺失表 + 更新 GSI（支持 `--dry-run`） |
| `odm:schema-tool:drop` | 删除所有托管 Item 对应的表 |

---

## 异常体系

| 异常类 | 场景 |
|--------|------|
| `ODMException` | 通用 ODM 错误（类型不匹配、字段未定义等） |
| `DataConsistencyException` | CAS 冲突 |
| `AnnotationParsingException` | Attribute 解析错误 |
| `NotAnnotatedException` | 类未标注 `#[Item]` |
| `UnderlyingDatabaseException` | 底层数据库返回异常结果 |
