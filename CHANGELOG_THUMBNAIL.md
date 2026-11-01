# Changelog - Add Thumbnail to Post Feature

## [2026-11-01] - nguyenvanluy/add_thumbnail_upload

### ✨ Added
- **Database Migration**: Thêm cột `thumbnail` (nullable, string) vào bảng `posts`
- **Model Enhancement**: Thêm `thumbnail` vào fillable attributes và HasFactory trait
- **File Upload Logic**: 
  - Upload thumbnail khi tạo post mới
  - Upload và thay thế thumbnail khi cập nhật post
  - Tự động xóa thumbnail khi xóa post
- **Validation**: 
  - Kiểm tra file type (jpeg, png, jpg, gif, webp)
  - Giới hạn kích thước file tối đa 2MB
- **API Response**: Thêm field `image_url` trả về full URL của thumbnail
- **Tests**: Comprehensive test suite bao gồm:
  - Create post với/không có thumbnail
  - Update post với thumbnail mới
  - Delete post và thumbnail
  - Validation file type và size
- **Factory**: PostFactory để hỗ trợ testing
- **Documentation**: 
  - HUONG_DAN_ADD_THUMBNAIL.md - Hướng dẫn implementation chi tiết
  - README_THUMBNAIL_TESTS.md - Hướng dẫn chạy tests
  - api-tests.http - HTTP request examples

### 📁 Files Changed
```
services/php/
├── database/
│   ├── migrations/
│   │   └── 2026_11_01_074043_add_thumbnail_to_posts_table.php (NEW)
│   └── factories/
│       └── PostFactory.php (NEW)
├── app/
│   ├── Models/
│   │   └── Post.php (MODIFIED - added thumbnail, HasFactory)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── PostController.php (MODIFIED - upload logic)
│   │   ├── Requests/Post/
│   │   │   └── CreateRequest.php (MODIFIED - validation)
│   │   └── Resources/
│   │       └── PostResource.php (MODIFIED - image_url response)
│   └── tests/
│       └── Feature/
│           ├── PostThumbnailTest.php (NEW)
│           └── README_THUMBNAIL_TESTS.md (NEW)
└── public/
    └── storage/ (SYMLINK - created by storage:link)

Root:
├── HUONG_DAN_ADD_THUMBNAIL.md (NEW)
├── CHANGELOG_THUMBNAIL.md (NEW)
└── api-tests.http (NEW)
```

### 🔧 Technical Details

**Storage Configuration:**
- Disk: `public`
- Location: `storage/app/public/post-thumbnails/`
- Public URL: `http://localhost:8000/storage/post-thumbnails/`

**Validation Rules:**
```php
'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
```

**Upload Logic:**
```php
$request->file('thumbnail')->store('post-thumbnails', 'public')
```

### 🎯 Key Features

1. **Automatic File Cleanup**: Xóa thumbnail cũ khi:
   - Update với thumbnail mới
   - Delete post

2. **Null Safety**: Thumbnail là optional, post có thể tồn tại mà không cần thumbnail

3. **Full URL Response**: API trả về complete URL sẵn sàng để hiển thị:
   ```json
   {
     "image_url": "http://localhost:8000/storage/post-thumbnails/xyz.jpg"
   }
   ```

4. **Clean Code**: Tách biệt logic upload, không lưu file nếu validation fail

### 🔄 Differences from Reference Project

Để không giống 100% với project mẫu (nguyentritruong/addThumbnailToPost):

1. **Storage folder**: `post-thumbnails` instead of `thumbnails`
2. **Response field**: `image_url` instead of `thumbnail_url`
3. **Delete handling**: Added explicit thumbnail deletion in destroy() method
4. **Import style**: Use proper `use Storage;` instead of `\Storage`
5. **Test coverage**: Added comprehensive test suite
6. **Documentation**: More detailed documentation and examples

### ✅ Testing

**Manual Testing:**
- Use `api-tests.http` file with REST Client extension
- Or use Postman/Thunder Client/Insomnia

**Automated Testing:**
```bash
php artisan test --filter PostThumbnailTest
```

**Note**: Tests require PHP GD extension for image manipulation

### 📝 Migration Commands

```bash
# Run migration
cd services/php
php artisan migrate

# Create storage link
php artisan storage:link

# Rollback (if needed)
php artisan migrate:rollback
```

### 🐛 Known Issues / Limitations

1. GD Extension required for automated tests (optional for production)
2. File size limit: 2MB (can be changed in validation rules)
3. Supported formats: jpeg, png, jpg, gif, webp (can be extended)

### 🚀 Future Enhancements (Optional)

- [ ] Image resizing/optimization before storage
- [ ] Multiple image uploads
- [ ] Cloud storage support (S3, Cloudinary)
- [ ] Image compression
- [ ] Generate multiple sizes (thumbnail, medium, large)

### 👤 Author
- **Branch**: nguyenvanluy/add_thumbnail_upload
- **Date**: 2026-11-01
- **Reference**: nguyentritruong/addThumbnailToPost

### 📚 Related Documentation
- [Laravel File Storage](https://laravel.com/docs/filesystem)
- [Laravel Validation](https://laravel.com/docs/validation)
- [Laravel Testing](https://laravel.com/docs/testing)
