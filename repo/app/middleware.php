<?php

// Global middleware (applied to all requests)
return [
    \think\middleware\SessionInit::class,
    \app\middleware\RequestIdMiddleware::class,
];
