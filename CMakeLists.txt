cmake_minimum_required(VERSION 3.18)
project(md5_bruteforce LANGUAGES CXX CUDA)

set(CMAKE_CXX_STANDARD 14)
set(CMAKE_CUDA_STANDARD 14)

add_executable(md5_bruteforce main.cu)
set_target_properties(md5_bruteforce PROPERTIES
    CUDA_SEPARABLE_COMPILATION ON
)
