# ============================================
# 优化后的 Laravel Dockerfile
# 遵循 user_rule.md 规范：Alpine + 层缓存优化
# ============================================

FROM composer:2 AS composer

# ============================================
# 生产环境
FROM php:8.2-cli-alpine

WORKDIR /app

# 安装 PHP 扩展依赖（使用 Alpine 减小镜像体积）
RUN apk add --no-cache \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql bcmath gd zip

# 复制 Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# 创建 Laravel 项目框架（缓存此层）
RUN composer create-project --prefer-dist laravel/laravel . --no-interaction

# 复制应用代码（覆盖默认文件）
COPY . .

# 配置环境并安装 Sanctum
RUN cp .env.example .env 2>/dev/null || true \
    && php artisan key:generate --ansi 2>/dev/null || true \
    && composer require laravel/sanctum --no-interaction \
    && php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --ansi \
    && composer install --no-interaction --optimize-autoloader --no-dev || true

EXPOSE 8080

# 启动前先同步弱项画像相关的增量表结构（migration 均带 IF NOT EXISTS 保护，幂等安全），
# 再启动服务；画像表与正式成绩表相互独立，不会改动既有成绩数据。
CMD ["sh", "-c", "php artisan migrate --force || true; php artisan serve --host=0.0.0.0 --port=8080"]
