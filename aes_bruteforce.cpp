#include <openssl/aes.h>
#include <iostream>
#include <iomanip>
#include <cstring>
#include <cstdint>

void hexToBytes(const std::string &hex, uint8_t *out){
    for(size_t i=0;i<hex.size()/2;i++){
        std::string byte = hex.substr(i*2,2);
        out[i] = static_cast<uint8_t>(strtol(byte.c_str(), nullptr, 16));
    }
}

int main(int argc, char **argv){
    if(argc < 3){
        std::cerr << "Usage: " << argv[0] << " <plaintext_hex> <ciphertext_hex>" << std::endl;
        return 1;
    }
    std::string plainHex = argv[1];
    std::string cipherHex = argv[2];
    if(plainHex.size() != 32 || cipherHex.size() != 32){
        std::cerr << "Expect 32 hex chars for plaintext and ciphertext" << std::endl;
        return 1;
    }
    uint8_t plaintext[16], target[16];
    hexToBytes(plainHex, plaintext);
    hexToBytes(cipherHex, target);

    uint8_t key[16] = {0};
    uint8_t out[16];
    AES_KEY aesKey;
    for(uint32_t k=0; k <= 0xFFFF; ++k){
        key[0] = static_cast<uint8_t>(k >> 8);
        key[1] = static_cast<uint8_t>(k & 0xFF);
        AES_set_encrypt_key(key, 128, &aesKey);
        AES_encrypt(plaintext, out, &aesKey);
        if(std::memcmp(out, target, 16) == 0){
            std::cout << "Found key: " << std::hex << std::setw(2) << std::setfill('0')
                      << static_cast<int>(key[0])
                      << static_cast<int>(key[1]) << std::dec << std::endl;
            return 0;
        }
    }
    std::cout << "Key not found" << std::endl;
    return 0;
}
