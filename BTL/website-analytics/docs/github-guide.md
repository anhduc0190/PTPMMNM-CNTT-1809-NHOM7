# HƯỚNG DẪN QUẢN LÝ MÃ NGUỒN GIT VÀ GITHUB

**Đề tài 17:** Xây dựng công cụ thống kê lượt truy cập và thao tác trên website  

---

## 1. Nguyên Tắc Quản Lý Mã Nguồn

1. **Không commit bí mật (Secrets):** File `.gitignore` đã loại trừ toàn bộ mật khẩu cục bộ, file tạm, file log và cache.
2. **Commit phản ánh thay đổi thực tế:** Mỗi commit gắn liền với một giai đoạn cụ thể của đề tài.
3. **Bảo mật token cá nhân:** Không bao giờ gửi Personal Access Token (PAT) hoặc mật khẩu GitHub vào khung chat.

---

## 2. Các Lệnh Git Hằng Ngày Cho Sinh Viên

### Kiểm tra trạng thái thay đổi
```powershell
git status
```

### Xem chi tiết các dòng mã đã thay đổi
```powershell
git diff
```

### Thêm file vào staging và tạo Commit theo mốc
```powershell
git add .
git commit -m "feat(analytics): hoan thanh he thong thong ke website va bo test rubric 12 tieu chi"
```

---

## 3. Hướng Dẫn Kết Nối GitHub Repository

Nếu bạn muốn tạo một repository mới trên tài khoản GitHub của mình:

### Bước 1: Tạo Repository trống trên GitHub
1. Đăng nhập [GitHub](https://github.com/).
2. Nhấn vào biểu tượng **+** ở góc trên bên phải $\rightarrow$ Chọn **New repository**.
3. Đặt tên repository: `website-analytics` (chọn chế độ Public hoặc Private).
4. **Không** tích chọn *"Add a README file"*, *"Add .gitignore"* vì dự án đã có sẵn.
5. Nhấn **Create repository**.

### Bước 2: Thêm Remote Origin và Đẩy Code Lên
Tại thư mục chứa mã nguồn dự án:
```powershell
# Kiem tra remote hien tai
git remote -v

# Neu muon doi sang repo ca nhan cua ban:
git remote set-url origin https://github.com/<YOUR_USERNAME>/website-analytics.git

# Day nhanh len branch main
git branch -M main
git push -u origin main
```

---

## 4. Hướng Dẫn Clone Và Khôi Phục Dự Án Trên Máy Khác

Khi giảng viên hoặc thành viên nhóm muốn tải và chạy lại dự án trên một máy tính Windows mới:
```powershell
# 1. Clone repository ve may
git clone https://github.com/<YOUR_USERNAME>/website-analytics.git
cd website-analytics

# 2. Khoi tao co so du lieu
Get-Content database/schema.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root
Get-Content database/seed.sql | & "C:\xampp\mysql\bin\mysql.exe" -u root

# 3. Chay bo test de kiem tra toan ven
& "C:\xampp\php\php.exe" scripts/run-tests.php

# 4. Khoi dong ung dung
& "C:\xampp\php\php.exe" -S localhost:8000
```
Truy cập: `http://localhost:8000/`.
