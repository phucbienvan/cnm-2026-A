# HƯỚNG DẪN THÊM THUMBNAIL CHO POST

## 📋 Tổng quan
Tài liệu này hướng dẫn chi tiết cách implement tính năng upload và hiển thị thumbnail cho bài viết (Post) trong Laravel.

## 🎯 Mục tiêu
- Thêm cột `thumbnail` vào bảng `posts`
- Cho phép user upload ảnh thumbnail khi tạo/cập nhật post
- Lưu trữ file trong `storage/app/public`
- Hiển thị thumbnail URL trong API response
- Validate file upload (image, size, format)

---

## 📦 Chuẩn bị

### 1. Kiểm tra môi trường
- ✅ Laravel đã cài đặt
- ✅ Database đã kết nối
- ✅ Storage link đã được tạo (nếu chưa, chạy: `php artisan storage:link`)

### 2. Các file sẽ được tạo/chỉnh sửa
```
services/php/
├── database/migrations/
│   └── YYYY_MM_DD_HHMMSS_add_thumbnail_to_posts_table.php   [TẠO MỚI]
├── app/Models/
│   └── Post.php                                              [CHỈNH SỬA]
├── app/Http/Controllers/
│   └── PostController.php                                    [CHỈNH SỬA]
├── app/Http/Requests/Post/
│   ├── CreateRequest.php                                     [CHỈNH SỬA]
│   └── UpdateRequest.php                                     [CHỈNH SỬA]
└── app/Http/Resources/
    └── PostResource.php                                      [CHỈNH SỬA]
```

---

## 🚀 Các bước thực hiện

### BƯỚC 1: Tạo Migration thêm cột thumbnail

**Mục đích:** Thêm cột `thumbnail` (nullable, string) vào bảng `posts`

**Tham khảo từ project mẫu:**
```php
// Migration: add_thumbnail_to_posts_table.php
Schema::table('posts', function (Blueprint $table) {
    $table->string('thumbnail')->nullable()->after('content');
});
```

**Thực hiện:**
```bash
cd services/php
php artisan make:migration add_thumbnail_to_posts_table --table=posts
```

**Nội dung file migration:**
- Thêm cột `thumbnail` kiểu `string`, `nullable`, đặt sau cột `content`
- Method `down()` phải drop cột `thumbnail`

---

### BƯỚC 2: Cập nhật Model Post

**Mục đích:** Thêm `thumbnail` vào `$fillable` để cho phép mass assignment

**Tham khảo từ project mẫu:**
```php
protected $fillable = [
    'title',
    'content',
    'thumbnail',  // <-- Thêm dòng này
    'user_id',
];
```

**Thực hiện:**
- Mở file `services/php/app/Models/Post.php`
- Thêm `'thumbnail'` vào mảng `$fillable`

---

### BƯỚC 3: Cập nhật Validation Request

**Mục đích:** Validate file upload thumbnail

**Tham khảo từ project mẫu:**
```php
// CreateRequest.php
public function rules(): array
{
    return [
        'title'     => 'required|string|max:255',
        'content'   => 'required|string',
        'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
    ];
}
```

**Thực hiện:**
- Mở `services/php/app/Http/Requests/Post/CreateRequest.php`
- Thêm rule cho `thumbnail`: `nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048`
- Làm tương tự cho `UpdateRequest.php` (nếu có)

**Giải thích validation:**
- `nullable`: Thumbnail không bắt buộc
- `image`: Phải là file ảnh
- `mimes`: Chỉ chấp nhận jpeg, png, jpg, gif, webp
- `max:2048`: Kích thước tối đa 2MB

---

### BƯỚC 4: Cập nhật PostController

**Mục đích:** Xử lý upload file và lưu đường dẫn vào database

**Tham khảo từ project mẫu:**
```php
// PostController.php - store method
public function store(CreateRequest $request)
{
    $data = $request->validated();

    // Xử lý upload thumbnail
    if ($request->hasFile('thumbnail')) {
        $data['thumbnail'] = $request->file('thumbnail')
            ->store('thumbnails', 'public');
    }

    $data['user_id'] = auth()->id();
    
    $post = Post::create($data);

    return response()->json([
        'message' => 'Post created successfully',
        'data'    => new PostResource($post),
    ], 201);
}
```

**Logic upload:**
1. Kiểm tra có file `thumbnail` không bằng `$request->hasFile('thumbnail')`
2. Nếu có, lưu file vào thư mục `storage/app/public/thumbnails`
3. Method `store('thumbnails', 'public')` trả về đường dẫn như: `thumbnails/abc123.jpg`
4. Lưu đường dẫn này vào database

**Thực hiện:**
- Mở `services/php/app/Http/Controllers/PostController.php`
- Thêm logic upload vào method `store()`
- Thêm logic tương tự vào method `update()` (nếu cần)

**Lưu ý cho update:**
- Nếu có thumbnail mới, xóa thumbnail cũ trước khi upload mới
- Sử dụng `Storage::disk('public')->delete($oldThumbnail)`

---

### BƯỚC 5: Cập nhật PostResource

**Mục đích:** Trả về full URL của thumbnail trong API response

**Tham khảo từ project mẫu:**
```php
use Illuminate\Support\Facades\Storage;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'content'       => $this->content,
            'thumbnail_url' => $this->thumbnail
                                ? Storage::disk('public')->url($this->thumbnail)
                                : null,
            'user_id'       => $this->user_id,
            'user'          => new UserResource($this->user),
        ];
    }
}
```

**Giải thích:**
- `Storage::disk('public')->url()` chuyển đổi path `thumbnails/abc.jpg` 
  thành URL: `http://localhost/storage/thumbnails/abc.jpg`
- Nếu không có thumbnail, trả về `null`

**Thực hiện:**
- Mở `services/php/app/Http/Resources/PostResource.php`
- Import `Storage` facade
- Thêm field `thumbnail_url` vào response array

---

### BƯỚC 6: Chạy Migration

```bash
cd services/php
php artisan migrate
```

**Kiểm tra:**
- Vào database, xác nhận cột `thumbnail` đã được thêm vào bảng `posts`

---

### BƯỚC 7: Tạo Symbolic Link (nếu chưa có)

```bash
cd services/php
php artisan storage:link
```

**Giải thích:**
- Tạo symlink từ `public/storage` -> `storage/app/public`
- Cho phép truy cập file từ web browser

---

## ✅ Kiểm tra và Test

### 1. Test upload thumbnail qua Postman/Thunder Client

**API Endpoint:** `POST /api/posts`

**Headers:**
```
Authorization: Bearer {your_token}
Content-Type: multipart/form-data
```

**Body (form-data):**
```
title: "Test Post with Thumbnail"
content: "This is a test post"
thumbnail: [chọn file ảnh]
```

**Expected Response:**
```json
{
    "message": "Post created successfully",
    "data": {
        "id": 1,
        "title": "Test Post with Thumbnail",
        "content": "This is a test post",
        "thumbnail_url": "http://localhost:8000/storage/thumbnails/abc123xyz.jpg",
        "user_id": 1,
        "user": {...}
    }
}
```

### 2. Kiểm tra file đã được lưu

- Kiểm tra thư mục: `services/php/storage/app/public/thumbnails/`
- File ảnh phải tồn tại với tên ngẫu nhiên (hash)

### 3. Kiểm tra truy cập URL

- Mở browser, paste URL từ `thumbnail_url`
- Ảnh phải hiển thị được

### 4. Test validation

**Test case 1: Upload file không phải ảnh**
- Expected: Lỗi validation `The thumbnail must be an image.`

**Test case 2: Upload file > 2MB**
- Expected: Lỗi validation `The thumbnail may not be greater than 2048 kilobytes.`

**Test case 3: Upload file format không hỗ trợ (.bmp, .tiff)**
- Expected: Lỗi validation về `mimes`

---

## 🎨 Điểm khác biệt so với project mẫu

Để tránh giống 100% với project mẫu, bạn có thể tùy chỉnh:

### 1. Thay đổi tên thư mục lưu trữ
```php
// Thay vì 'thumbnails'
$data['thumbnail'] = $request->file('thumbnail')
    ->store('post-images', 'public');  // <-- Đổi tên thư mục
```

### 2. Thay đổi tên field trong response
```php
// Thay vì 'thumbnail_url'
return [
    // ...
    'image_url' => $this->thumbnail ? Storage::disk('public')->url($this->thumbnail) : null,
    // hoặc
    'cover_image' => ...
];
```

### 3. Thêm xử lý resize ảnh (nâng cao)
```php
use Intervention\Image\Facades\Image;

// Resize thumbnail trước khi lưu
if ($request->hasFile('thumbnail')) {
    $image = $request->file('thumbnail');
    $filename = time() . '.' . $image->getClientOriginalExtension();
    $path = storage_path('app/public/thumbnails/' . $filename);
    
    Image::make($image)->resize(800, 600, function ($constraint) {
        $constraint->aspectRatio();
    })->save($path);
    
    $data['thumbnail'] = 'thumbnails/' . $filename;
}
```

### 4. Thêm helper method trong Model
```php
// Post.php
public function getThumbnailUrlAttribute()
{
    return $this->thumbnail 
        ? Storage::disk('public')->url($this->thumbnail)
        : asset('images/default-thumbnail.jpg');
}

// Sử dụng: $post->thumbnail_url
```

### 5. Thay đổi validation rules
```php
// Chặt chẽ hơn
'thumbnail' => 'nullable|image|mimes:jpeg,png|max:1024|dimensions:min_width=300,min_height=300',

// Hoặc lỏng hơn
'thumbnail' => 'nullable|file|max:5120',
```

---

## 🐛 Troubleshooting

### Lỗi: "The file could not be uploaded"
- Kiểm tra quyền write của thư mục `storage/app/public`
- Chạy: `chmod -R 775 storage`

### Lỗi: "Symbolic link could not be created"
- Xóa thư mục `public/storage` nếu đã tồn tại
- Chạy lại `php artisan storage:link`

### Thumbnail URL trả về 404
- Kiểm tra symbolic link: `ls -la public/storage`
- Kiểm tra file tồn tại: `ls storage/app/public/thumbnails`

### Lỗi: "Undefined property: thumbnail"
- Kiểm tra migration đã chạy chưa
- Kiểm tra `thumbnail` đã có trong `$fillable` chưa

---

## 📚 Tài liệu tham khảo

- Laravel File Storage: https://laravel.com/docs/filesystem
- File Uploads: https://laravel.com/docs/requests#files
- Validation Rules: https://laravel.com/docs/validation#available-validation-rules

---

## 📝 Checklist hoàn thành

- [ ] Migration đã tạo và chạy thành công
- [ ] Model đã thêm `thumbnail` vào `$fillable`
- [ ] Validation rules đã được thêm vào CreateRequest và UpdateRequest
- [ ] PostController xử lý upload trong store() và update()
- [ ] PostResource trả về `thumbnail_url` đúng định dạng
- [ ] Storage link đã được tạo
- [ ] Test API create post với thumbnail thành công
- [ ] File ảnh được lưu trong `storage/app/public/thumbnails`
- [ ] Truy cập URL từ response hiển thị được ảnh
- [ ] Validation hoạt động đúng (file type, size)

---

**Lưu ý cuối:** 
- Làm từng bước một, test sau mỗi bước
- Commit code sau mỗi bước hoàn thành
- Nếu gặp lỗi, đọc kỹ log và error message
- Tham khảo project mẫu khi cần, nhưng không copy 100%

Good luck! 🚀
