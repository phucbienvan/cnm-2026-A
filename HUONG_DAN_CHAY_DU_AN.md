# 🚀 Hướng Dẫn Chạy Dự Án CNM 2026

Tài liệu này lưu trữ chi tiết các bước khởi chạy và quản lý dự án để bạn có thể xem lại bất cứ lúc nào.

---

## 📁 Cấu trúc Dự án
- `compose.yaml`: Cấu hình Docker Compose chung (PHP Laravel, FastAPI, MySQL, Redis)
- `services/php/`: Nguồn ứng dụng Laravel (PHP 8.4 + Vite)
- `services/fastapi/`: Nguồn dịch vụ FastAPI (Python)

---

## 🐳 CÁCH 1: Chạy bằng Docker Compose (Khuyên dùng)

Yêu cầu: Đã cài đặt Docker Desktop và Docker Compose.

### 1. Lần đầu tiên khởi chạy (Setup)

Mở terminal tại thư mục gốc của dự án (`cnm-2026-A`) và thực hiện lần lượt các lệnh sau:

1. **Tạo các file cấu hình `.env` (nếu chưa có):**
   ```bash
   cp .env.example .env
   cp services/php/.env.example services/php/.env
   ```

2. **Cài đặt PHP Dependencies:**
   ```bash
   composer --working-dir=services/php install
   ```

3. **Build & Khởi động toàn bộ các Container:**
   ```bash
   docker compose up -d --build
   ```

4. **Khởi tạo Laravel (Key, Database, Assets):**
   ```bash
   # Tạo APP_KEY cho Laravel (chỉ cần chạy nếu chưa có key)
   docker compose exec php php artisan key:generate

   # Chạy Migration tạo các bảng trong Database
   docker compose exec php php artisan migrate

   # Cài đặt và build Frontend Vite
   docker compose exec php npm install
   docker compose exec php npm run build
   ```

---

### 2. Các lần chạy tiếp theo (Hàng ngày)

Khi cần làm việc, chỉ cần mở terminal tại thư mục gốc dự án:

* **Bật dự án:**
  ```bash
  docker compose up -d
  ```

* **Tắt dự án:**
  ```bash
  docker compose down
  ```

* **Xem Log của các service:**
  ```bash
  docker compose logs -f
  # Hoặc xem log riêng của php và fastapi:
  docker compose logs -f php fastapi
  ```

* **Chạy Vite cho Hot Reload khi phát triển Frontend:**
  ```bash
  docker compose exec php npm run dev -- --host 0.0.0.0
  ```

---

### 3. Thông tin Địa chỉ & Cổng truy cập (Ports)

| Service | Đường dẫn / Địa chỉ | Ghi chú |
|---|---|---|
| **Laravel App** | [http://localhost:8000](http://localhost:8000) *(hoặc cổng trong `.env` gốc)* | Giao diện chính dự án |
| **FastAPI Swagger API** | [http://localhost:8001/docs](http://localhost:8001/docs) | Tài liệu API FastAPI |
| **FastAPI Health** | [http://localhost:8001/health](http://localhost:8001/health) | Kiểm tra trạng thái FastAPI |
| **MySQL Database** | Host: `127.0.0.1`, Port: `3307` *(hoặc `3308` tùy `.env`)* | User: `sail`, Pass: `password`, DB: `laravel` |
| **Redis** | Host: `127.0.0.1`, Port: `6380` *(hoặc `6381` tùy `.env`)* | Cache & Queue |

---

## 💻 CÁCH 2: Chạy trực tiếp trên máy không qua Docker (Local)

Nếu không dùng Docker, bạn cần có PHP >= 8.3, Composer, Node.js, Python 3.10+, MySQL server và Redis server trên máy local.

### 1. Chạy Backend Laravel (`services/php`)
```bash
cd services/php

# Cài gói
composer install
npm install

# Cấu hình môi trường (.env)
cp .env.example .env
# Sửa thông tin DB_HOST=127.0.0.1, DB_PORT=3306,... trong .env phù hợp với MySQL trên máy

php artisan key:generate
php artisan migrate

# Bật Web Server Laravel
php artisan serve

# Bật Vite Frontend (Mở thêm 1 terminal mới tại services/php)
npm run dev
```

### 2. Chạy API FastAPI (`services/fastapi`)
```bash
cd services/fastapi

# Tạo môi trường ảo python
python -m venv venv

# Kích hoạt venv (Windows PowerShell)
.\venv\Scripts\Activate.ps1

# Cài đặt thư viện
pip install -r requirements.txt

# Bật FastAPI server
uvicorn app.main:app --reload --port 8001
```

---

## 🛠 Lệnh tiện ích thường dùng

* **Chạy Unit Test Laravel:**
  ```bash
  docker compose exec php php artisan test
  ```
* **Re-build lại khi sửa `requirements.txt` FastAPI:**
  ```bash
  docker compose up -d --build fastapi
  ```
* **Chạy lại Migration reset cơ sở dữ liệu:**
  ```bash
  docker compose exec php php artisan migrate:fresh --seed
  ```
