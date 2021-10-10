# Laravel Practice

## Static Analysis Tool
- larastan
- PHP Coding Standards Fixer

## test実行前に事前準備が必要
- PHPUnitを動かすための準備が必要

### テーブル作成
```mysql
create schema type_89_test;
```

### ユーザー作成
```mysql
create user type_89_test identified by 'password';
```

### 権限追加
```mysql
GRANT all ON type_89_test.* TO sail_test;
```
### .env.test修正
- 以下に書き換える
```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=type_89_test
DB_USERNAME=sail_test
DB_PASSWORD=password
```
