# tastchatopenai

测试chatopenai

## MD5 GPU Brute Force

使用CUDA实现的简单MD5暴力破解示例，支持可选的盐值（前缀或后缀）。

### 构建

```bash
cmake -S . -B build
cmake --build build
```

### 运行

```bash
./build/md5_bruteforce <md5hash> <length> [salt] [prefix|suffix]
```

例如:
```bash
./build/md5_bruteforce 5d41402abc4b2a76b9719d911017c592 5
```
将尝试在所有5位小写字母组合中寻找匹配`hello`的MD5值。
再例如，带盐的情况:
```bash
./build/md5_bruteforce 70fb874a43097a25234382390c0baeb3 3 xyz suffix
```
将在候选字符串后追加`xyz`进行匹配。

## AES Key Brute Force

`aes_bruteforce`使用OpenSSL在CPU上暴力搜索16位AES密钥，用于演示如何根据明文和密文对寻找密钥。

```bash
./build/aes_bruteforce <plaintext_hex16> <ciphertext_hex16>
```
