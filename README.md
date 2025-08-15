# tastchatopenai

测试chatopenai

## MD5 GPU Brute Force

使用CUDA实现的简单MD5暴力破解示例。

### 构建

```bash
cmake -S . -B build
cmake --build build
```

### 运行

```bash
./build/md5_bruteforce <md5hash> <length>
```

例如:
```bash
./build/md5_bruteforce 5d41402abc4b2a76b9719d911017c592 5
```
将尝试在所有5位小写字母组合中寻找匹配`hello`的MD5值。
