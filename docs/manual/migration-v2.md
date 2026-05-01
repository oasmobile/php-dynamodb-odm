# Migration Guide: 1.x → 2.0

从 oasis/dynamodb-odm 1.x 升级到 2.0 的迁移指南。

---

## 环境要求

| 项 | 1.x | 2.0 |
|----|-----|-----|
| PHP | ≥ 7.1 | ≥ 8.5 |
| PHPUnit | ^5.7 | ^13.0 |
| `symfony/console` | ^4.4 | ^7.0 |
| `symfony/finder` | ^4.4 | ^7.0 |

---

## Breaking Changes

### 1. 命名空间：`Annotations` → `Attributes`

所有 Attribute 类的命名空间从 `Oasis\Mlib\ODM\Dynamodb\Annotations` 改为 `Oasis\Mlib\ODM\Dynamodb\Attributes`。

**操作**：全局替换 `use` 语句。

```diff
-use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
-use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;
-use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
-use Oasis\Mlib\ODM\Dynamodb\Annotations\PartitionedHashKey;
+use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
+use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
+use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
+use Oasis\Mlib\ODM\Dynamodb\Attributes\PartitionedHashKey;
```

### 2. 注解语法：docblock → PHP 8 原生 Attribute

`doctrine/annotations` 的 docblock 注解（`@Item`、`@Field` 等）已替换为 PHP 8 原生 `#[Attribute]` 语法。

**操作**：将所有 Item 类的 docblock 注解改写为 `#[...]` 语法。

```diff
-/**
- * @Item(
- *     table="users",
- *     primaryIndex={"id"}
- * )
- */
+#[Item(
+    table: 'users',
+    primaryIndex: new Index(hash: 'id'),
+)]
 class User
 {
-    /** @Field(type="number") */
-    protected $id;
+    #[Field(type: 'number')]
+    protected int $id = 0;
 }
```

注意事项：

- `primaryIndex`、`globalSecondaryIndices`、`localSecondaryIndices` 中的索引需使用 `new Index(hash: '...', range: '...')` 语法
- plain array 写法（如 `primaryIndex: ['id']`）仍然兼容，但推荐使用 `new Index(...)` 以获得 IDE 支持
- `cas` 值推荐使用常量：`Field::CAS_ENABLED`、`Field::CAS_TIMESTAMP`

### 3. `ItemManager` 构造函数：4 参数 → 2 参数

`$cacheDir` 和 `$isDev` 参数已移除（不再需要 Doctrine 注解缓存）。

**操作**：移除构造函数中的第 3、4 个参数。

```diff
 $im = new ItemManager(
     ['profile' => 'my-aws-profile', 'region' => 'ap-northeast-1'],
     'myapp-',
-    __DIR__ . '/cache',
-    true
 );
```

### 4. 移除的方法

以下 `ItemManager` 方法已移除，如有调用需删除：

| 方法 | 说明 |
|------|------|
| `getReader()` | 不再使用 Doctrine `AnnotationReader` |
| `loadAnnotationClass()` | 不再需要注解类加载回调 |

---

## 移除的依赖

以下依赖已从 `composer.json` 中移除，升级后可清理相关配置：

- `doctrine/annotations`
- `doctrine/common`

如果项目中有 Doctrine 注解缓存目录（如 `cache/`），可以安全删除。

---

## 迁移步骤

1. 升级 PHP 到 8.5+
2. 更新 `composer.json` 中的 `oasis/dynamodb-odm` 版本约束，运行 `composer update`
3. 全局替换命名空间（`Annotations` → `Attributes`）
4. 将所有 Item 类的 docblock 注解改写为 `#[Attribute]` 语法
5. 修改 `ItemManager` 构造调用，移除 `$cacheDir` 和 `$isDev` 参数
6. 删除对 `getReader()` / `loadAnnotationClass()` 的调用（如有）
7. 删除注解缓存目录（如有）
8. 运行测试验证
