# Hướng dẫn chạy PostThumbnailTest

## ⚠️ Yêu cầu

Tests sử dụng `UploadedFile::fake()->image()` cần PHP GD extension.

### Kiểm tra GD extension

```bash
php -m | grep -i gd
```

Nếu không thấy "gd", bạn cần enable nó trong `php.ini`:

1. Tìm file `php.ini`:
   ```bash
   php --ini
   ```

2. Mở file và bỏ comment dòng:
   ```ini
   ;extension=gd
   # Thành:
   extension=gd
   ```

3. Restart PHP/webserver

## 🧪 Chạy tests

```bash
# Chạy tất cả tests thumbnail
php artisan test --filter PostThumbnailTest

# Chạy test cụ thể
php artisan test --filter test_can_create_post_with_thumbnail
```

## ✅ Test coverage

- ✅ Tạo post có thumbnail
- ✅ Tạo post không có thumbnail
- ✅ Update post với thumbnail mới
- ✅ Xóa post tự động xóa thumbnail
- ✅ Validation file type
- ✅ Validation file size

## 📝 Lưu ý

Nếu không thể cài GD extension, bạn có thể test thủ công qua API (xem HUONG_DAN_ADD_THUMBNAIL.md)
