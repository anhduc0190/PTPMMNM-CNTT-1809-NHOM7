# Lab02 — Bài thực hành dành cho sinh viên

Nhánh này chỉ chứa một bài lab. Dùng Ubuntu/WSL, PHP từ 8.1 và Git.

Không cần PostgreSQL hoặc Composer.

## Chuẩn bị trên Ubuntu/WSL

```bash
sudo apt update
sudo apt install php-cli git unzip
php -v
git --version
```

PHP cần từ 8.1 trở lên. Các lệnh bên dưới chạy trong Ubuntu/WSL. Khi tải nhiều bài, trở về thư mục cha chứa các thư mục lab trước mỗi lệnh clone; không clone bài tiếp theo bên trong `Ma_nguon` của bài trước.

## Tải bài

```bash
git clone --single-branch --branch lab2 https://github.com/FIT-DNU/Open-Source-Software-Development.git lab2
cd lab2/Ma_nguon
```

## Chạy bài

```bash
bash lab.sh check
bash lab.sh run
```

Kết quả hiển thị trong terminal; không cần trình duyệt.

## Kiểm tra

Mở terminal thứ hai tại Ma_nguon, hoặc dừng máy chủ trước:

```bash
bash lab.sh test
bash lab.sh extend
```

test kiểm tra phần mẫu; extend kiểm tra các trạng thái đăng ký, dữ liệu sai và biên.

[Hướng dẫn đầy đủ](HUONG_DAN.md) · [Hướng dẫn mã nguồn](Ma_nguon/README.md)

Không đưa .local, mật khẩu hoặc dữ liệu cá nhân thật vào bài nộp.

## Các bài khác

[Lab01](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab1) · [Lab02](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab2) · [Lab03](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab3) · [Lab04](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab4) · [Lab05](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab5)

Kết quả yêu cầu: `TESTS=5 FAILED=0` và `TESTS=9 FAILED=0`. `check` chỉ kiểm tra môi trường; chạy `test` và `extend` để xác nhận bài lab.
