#include <cuda_runtime.h>
#include <iostream>
#include <iomanip>
#include <cstring>
#include <cstdint>

constexpr int MAX_LEN = 8;
__device__ __constant__ char d_alphabet[] = "abcdefghijklmnopqrstuvwxyz";

__device__ uint4 md5_device(const char *msg, int len) {
    uint32_t r[] = {
        7,12,17,22, 7,12,17,22, 7,12,17,22, 7,12,17,22,
        5,9,14,20, 5,9,14,20, 5,9,14,20, 5,9,14,20,
        4,11,16,23, 4,11,16,23, 4,11,16,23, 4,11,16,23,
        6,10,15,21, 6,10,15,21, 6,10,15,21, 6,10,15,21 };
    uint32_t k[] = {
        0xd76aa478,0xe8c7b756,0x242070db,0xc1bdceee,
        0xf57c0faf,0x4787c62a,0xa8304613,0xfd469501,
        0x698098d8,0x8b44f7af,0xffff5bb1,0x895cd7be,
        0x6b901122,0xfd987193,0xa679438e,0x49b40821,
        0xf61e2562,0xc040b340,0x265e5a51,0xe9b6c7aa,
        0xd62f105d,0x02441453,0xd8a1e681,0xe7d3fbc8,
        0x21e1cde6,0xc33707d6,0xf4d50d87,0x455a14ed,
        0xa9e3e905,0xfcefa3f8,0x676f02d9,0x8d2a4c8a,
        0xfffa3942,0x8771f681,0x6d9d6122,0xfde5380c,
        0xa4beea44,0x4bdecfa9,0xf6bb4b60,0xbebfbc70,
        0x289b7ec6,0xeaa127fa,0xd4ef3085,0x04881d05,
        0xd9d4d039,0xe6db99e5,0x1fa27cf8,0xc4ac5665,
        0xf4292244,0x432aff97,0xab9423a7,0xfc93a039,
        0x655b59c3,0x8f0ccc92,0xffeff47d,0x85845dd1,
        0x6fa87e4f,0xfe2ce6e0,0xa3014314,0x4e0811a1,
        0xf7537e82,0xbd3af235,0x2ad7d2bb,0xeb86d391 };

    uint32_t a0 = 0x67452301;
    uint32_t b0 = 0xefcdab89;
    uint32_t c0 = 0x98badcfe;
    uint32_t d0 = 0x10325476;

    uint8_t msgPad[64] = {0};
    for(int i=0;i<len;i++) msgPad[i] = static_cast<uint8_t>(msg[i]);
    msgPad[len] = 0x80;
    uint64_t bitLen = len * 8;
    memcpy(msgPad + 56, &bitLen, sizeof(uint64_t));
    uint32_t *w = reinterpret_cast<uint32_t*>(msgPad);

    uint32_t a=a0, b=b0, c=c0, d=d0;
    for(int i=0;i<64;i++) {
        uint32_t f, g;
        if (i < 16) { f = (b & c) | (~b & d); g = i; }
        else if (i < 32) { f = (d & b) | (~d & c); g = (5*i + 1) % 16; }
        else if (i < 48) { f = b ^ c ^ d; g = (3*i + 5) % 16; }
        else { f = c ^ (b | ~d); g = (7*i) % 16; }
        uint32_t temp = d;
        d = c;
        c = b;
        uint32_t rotate = a + f + k[i] + w[g];
        b = b + ((rotate << r[i]) | (rotate >> (32 - r[i])));
        a = temp;
    }
    a0 += a;
    b0 += b;
    c0 += c;
    d0 += d;
    return make_uint4(a0,b0,c0,d0);
}

__device__ bool hashes_equal(uint4 a, uint4 b){
    return a.x==b.x && a.y==b.y && a.z==b.z && a.w==b.w;
}

__global__ void brute_kernel(uint4 target, char *result, bool *found, int length, int alphaLen){
    unsigned long long idx = blockIdx.x*blockDim.x + threadIdx.x;
    unsigned long long total = 1;
    for(int i=0;i<length;i++) total *= alphaLen;
    if(idx >= total || *found) return;
    char candidate[MAX_LEN];
    unsigned long long tmp = idx;
    for(int i=0;i<length;i++){
        candidate[i] = d_alphabet[tmp % alphaLen];
        tmp /= alphaLen;
    }
    uint4 h = md5_device(candidate,length);
    if(hashes_equal(h, target)){
        if(!atomicExch(found,true)){
            for(int i=0;i<length;i++) result[i]=candidate[i];
            result[length]='\0';
        }
    }
}

uint4 parse_hash(const std::string &hex){
    uint8_t bytes[16];
    for(int i=0;i<16;i++){
        std::string byteString = hex.substr(i*2,2);
        bytes[i] = (uint8_t)strtol(byteString.c_str(), nullptr, 16);
    }
    uint4 val;
    val.x = bytes[0] | (bytes[1]<<8) | (bytes[2]<<16) | (bytes[3]<<24);
    val.y = bytes[4] | (bytes[5]<<8) | (bytes[6]<<16) | (bytes[7]<<24);
    val.z = bytes[8] | (bytes[9]<<8) | (bytes[10]<<16) | (bytes[11]<<24);
    val.w = bytes[12] | (bytes[13]<<8) | (bytes[14]<<16) | (bytes[15]<<24);
    return val;
}

int main(int argc, char **argv){
    if(argc < 3){
        std::cerr << "Usage: " << argv[0] << " <md5hash> <length>" << std::endl;
        return 1;
    }
    std::string hash = argv[1];
    int length = std::atoi(argv[2]);
    if(length > MAX_LEN){
        std::cerr << "Max length " << MAX_LEN << std::endl;
        return 1;
    }
    uint4 target = parse_hash(hash);

    char *d_result; bool *d_found;
    cudaMalloc(&d_result, MAX_LEN+1);
    cudaMalloc(&d_found, sizeof(bool));
    cudaMemset(d_found, 0, sizeof(bool));

    int alphaLen = 26;
    unsigned long long total = 1; for(int i=0;i<length;i++) total *= alphaLen;
    int threads = 256;
    int blocks = (total + threads -1)/threads;

    brute_kernel<<<blocks,threads>>>(target,d_result,d_found,length,alphaLen);
    cudaDeviceSynchronize();

    bool h_found=false; char h_result[MAX_LEN+1];
    cudaMemcpy(&h_found, d_found, sizeof(bool), cudaMemcpyDeviceToHost);
    if(h_found){
        cudaMemcpy(h_result, d_result, length+1, cudaMemcpyDeviceToHost);
        std::cout << "Found: " << h_result << std::endl;
    }else{
        std::cout << "Not found" << std::endl;
    }
    cudaFree(d_result); cudaFree(d_found);
    return 0;
}

