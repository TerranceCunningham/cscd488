<?php

namespace classes;

class Math {
    public function add(array $nums) {
        return $nums[0] + $nums[1];
    }

    public function sub(array $nums) {
        return $nums[0] - $nums[1];
    }

    public function mul(array $nums) {
        return $nums[0] * $nums[1];
    }
}