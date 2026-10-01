# Huong dan chay CNM 2026 bang Docker

Tai lieu nay huong dan chay toan bo he thong tren Windows PowerShell.

## 1. Yeu cau

Can cai dat va dang mo:

- Docker Desktop
- Docker Compose v2
- PHP >= 8.3
- Composer
- Node.js va npm

Kiem tra cong cu:

```powershell
docker --version
docker compose version
php -v
composer -V
node -v
npm -v
```

## 2. Di chuyen vao thu muc du an

Tat ca lenh Docker Compose phai chay tai thu muc goc:

```powershell
cd E:\TTCM_CNM
```

## 3. Cai dat lan dau

### Tao file cau hinh

Chi chay neu cac file chua ton tai:

```powershell
Copy-Item .env.example .env
Copy-Item services/php/.env.example services/php/.env
```

Khong ghi de file `.env` neu du an da co cau hinh rieng.

### Cai dependency Laravel

Dockerfile cua service PHP nam trong `vendor/laravel/sail`, vi vay can cai Composer truoc khi build:

```powershell
composer install --working-dir=services/php
```

### Build va khoi dong cac service

```powershell
docker compose up -d --build
```

Lenh nay khoi dong:

- `php`: Laravel va Vite
- `fastapi`: API Python
- `mysql`: co so du lieu
- `redis`: cache va queue

Kiem tra trang thai:

```powershell
docker compose ps
```

### Khoi tao Laravel

Chi tao application key neu `APP_KEY` trong `services/php/.env` dang trong:

```powershell
docker compose exec php php artisan key:generate
```

Chay migration:

```powershell
docker compose exec php php artisan migrate
```

Cai va build frontend:

```powershell
docker compose exec php npm install
docker compose exec php npm run build
```

## 4. Truy cap ung dung

Cac cong hien tai:

| Thanh phan | Dia chi |
| --- | --- |
| Laravel | http://localhost:8000 |
| FastAPI Swagger | http://localhost:8001/docs |
| FastAPI health | http://localhost:8001/health |
| MySQL tu may host | localhost:3307 |
| Redis tu may host | localhost:6380 |

FastAPI health phai tra ve:

```json
{"status":"ok"}
```

## 5. Lenh chay hang ngay

Sau khi da cai dat lan dau:

```powershell
cd E:\TTCM_CNM
docker compose up -d
docker compose ps
```

Mo Laravel tai http://localhost:8000.

## 6. Chay frontend development

De Vite tu dong cap nhat khi sua CSS hoac JavaScript:

```powershell
docker compose exec php npm run dev -- --host 0.0.0.0
```

Giu cua so terminal nay mo trong luc phat trien. Nhan `Ctrl+C` de dung Vite.

FastAPI tu dong reload khi sua file trong `services/fastapi/app`.

## 7. Xem log

Xem log PHP va FastAPI:

```powershell
docker compose logs -f php fastapi
```

Xem log cua mot service:

```powershell
docker compose logs -f php
docker compose logs -f fastapi
docker compose logs -f mysql
docker compose logs -f redis
```

Xem log tu luc khoi dong gan nhat:

```powershell
docker compose logs --tail=100 php fastapi
```

## 8. Artisan, Composer va test

Chay Artisan trong container PHP:

```powershell
docker compose exec php php artisan migrate
docker compose exec php php artisan optimize:clear
docker compose exec php php artisan route:list
```

Cai lai Composer dependency trong container:

```powershell
docker compose exec php composer install
```

Chay test:

```powershell
docker compose exec php php artisan test
```

## 9. Build lai service

Build lai toan bo sau khi thay doi Dockerfile hoac dependency:

```powershell
docker compose up -d --build
```

Chi build lai FastAPI sau khi sua `services/fastapi/requirements.txt`:

```powershell
docker compose up -d --build fastapi
```

Chi khoi dong lai mot service:

```powershell
docker compose restart php
docker compose restart fastapi
```

## 10. Dung va khoi dong lai

Dung container nhung giu nguyen volume du lieu:

```powershell
docker compose down
```

Khoi dong lai:

```powershell
docker compose up -d
```

Khong dung `-v` neu khong muon xoa du lieu MySQL va Redis:

```powershell
# Lenh nay xoa ca volume du lieu
# docker compose down -v
```

## 11. Kiem tra nhanh khi co loi

```powershell
docker compose ps
docker compose logs --tail=100 php fastapi
Invoke-WebRequest http://localhost:8000 -UseBasicParsing
Invoke-WebRequest http://localhost:8001/health -UseBasicParsing
```

Neu port bi trung, kiem tra cac gia tri sau trong file `.env` o thu muc goc:

```env
APP_PORT=8000
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
FASTAPI_PORT=8001
```

Cac service goi nhau trong mang Docker bang ten service:

```text
PHP -> FastAPI: http://fastapi:8000
PHP -> MySQL:   mysql:3306
PHP -> Redis:   redis:6379
```
