# CNM 2026

```text
compose.yaml    Docker Compose chung: PHP, FastAPI, MySQL, Redis
services/
  php/          Laravel (PHP), frontend Vite và tests
  fastapi/      FastAPI (Python)
```

## Chạy môi trường phát triển

Chạy các lệnh Docker Compose từ thư mục gốc. Cần Docker Compose v2.

Lần đầu clone, tạo cấu hình (bỏ qua nếu các file đã tồn tại):

```sh
cp .env.example .env
cp services/php/.env.example services/php/.env
```

Cài dependency Laravel trước khi build vì Dockerfile Sail nằm trong `vendor`:

```sh
composer --working-dir=services/php install
docker compose up -d --build
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
docker compose exec php npm install
docker compose exec php npm run build
```

Chỉ chạy `key:generate` khi `APP_KEY` chưa được thiết lập. Composer trên máy cần PHP >= 8.3.

- Laravel: http://localhost:8000
- FastAPI Swagger: http://localhost:8001/docs
- FastAPI health: http://localhost:8001/health
- MySQL: localhost:3307
- Redis: localhost:6380

Các cổng trên là mặc định trong `.env.example` ở gốc. Trên máy hiện tại, `.env` ở gốc
dùng Laravel `8002`, Vite `5174`, MySQL `3308`, Redis `6381` để tránh
xung đột với stack `cn-do` đang chạy; FastAPI vẫn dùng `8001`.

`.env` ở gốc chứa cấu hình Docker Compose (cổng, thông tin MySQL, UID/GID).
`services/php/.env` chứa cấu hình Laravel. Compose truyền thông tin đăng nhập MySQL
từ `.env` ở gốc vào container PHP.
Compose dùng tên project `cnm-2026-a` để giữ nguyên stack và dữ liệu khi chuyển thư mục.
MySQL và Redis giữ nguyên các volume `sail-mysql`, `sail-redis`.

FastAPI tự reload khi sửa code trong `services/fastapi/app`.
Khi sửa `requirements.txt`, chạy `docker compose up -d --build fastapi`.
Trong mạng Docker, PHP gọi FastAPI qua `http://fastapi:8000`;
FastAPI gọi PHP qua `http://php`.

## Lệnh thường dùng

```sh
docker compose exec php php artisan test
docker compose exec php composer install
docker compose exec php npm run dev -- --host 0.0.0.0
docker compose logs -f php fastapi
docker compose down
```

Các lệnh PHP/Composer chạy trực tiếp trên máy phải thực hiện trong `services/php`.
Cấu hình PHPUnit nằm ở `services/php/phpunit.xml`.
Dùng Docker Compose từ thư mục gốc để quản lý toàn bộ service.

Dockerfile FastAPI tham khảo [tài liệu Docker chính thức của FastAPI](https://fastapi.tiangolo.com/deployment/docker/).
