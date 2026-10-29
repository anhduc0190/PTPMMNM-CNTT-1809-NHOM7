# Web Analytics — Công cụ thống kê lượt truy cập và thao tác trên website

> Đồ án học phần: Đề tài 17 — Xây dựng công cụ thống kê lượt truy cập và thao tác trên website.

## 1. Giới thiệu dự án

**Web Analytics** là hệ thống thu thập, lưu trữ, phân tích và trực quan hóa dữ liệu truy cập trên các website thử nghiệm do nhóm quản lý.

Dự án hướng đến việc xây dựng một công cụ phân tích website với các chức năng cốt lõi:

- Thống kê lượt xem trang.
- Theo dõi các thao tác được cấu hình trên website.
- Thống kê phiên truy cập có hoạt động.
- Phân tích trang phổ biến và xu hướng truy cập theo thời gian.
- Phân tích chuỗi thao tác để xác định người dùng có hoàn thành quy trình hay không.
- Phát hiện sự kiện gửi trùng và xung đột dữ liệu.
- Xuất báo cáo và quản lý quyền truy cập.

Điểm trọng tâm của đề tài là **tự xây dựng cơ chế thu thập và tổng hợp dữ liệu**, bảo đảm các số liệu báo cáo có thể đối chiếu với sự kiện nguồn trong database.

Dự án sử dụng hai website thử nghiệm để kiểm tra toàn bộ quy trình từ phát sinh sự kiện đến hiển thị báo cáo.

## 2. Mục tiêu

1. Xây dựng JavaScript Tracking SDK để ghi nhận các sự kiện đã được cấu hình.
2. Xây dựng PHP API tiếp nhận, kiểm tra và lưu sự kiện.
3. Thiết kế database MySQL/MariaDB để quản lý website, phiên truy cập, sự kiện và quyền người dùng.
4. Xây dựng dashboard trực quan hóa dữ liệu thống kê.
5. Xử lý sự kiện gửi trùng, xung đột payload và sự kiện đến muộn theo quy tắc xác định.
6. Phân tích chuỗi thao tác trong cùng website và cùng phiên truy cập.
7. Xây dựng dữ liệu mẫu, chương trình kiểm thử và tài liệu bàn giao.

## 3. Công nghệ sử dụng

| Thành phần | Công nghệ |
|---|---|
| Backend | PHP |
| Database | MySQL/MariaDB |
| Web server | Apache thông qua XAMPP |
| Frontend | HTML5, CSS3, JavaScript |
| Biểu đồ | Chart.js |
| Database management | phpMyAdmin |
| Code editor | Visual Studio Code |
| Version control | Git và GitHub |

Dự án ưu tiên PHP thuần kết hợp PDO để dễ triển khai trên XAMPP và thuận tiện cho việc học tập, kiểm thử và giải thích mã nguồn.

## 4. Kiến trúc hệ thống

```text
Website A ─┐
           ├──> JavaScript Tracking SDK
Website B ─┘             |
                          v
                    PHP Tracking API
                          |
                Kiểm tra và xác thực
                          |
                Chống trùng sự kiện
                          |
                          v
                    MySQL/MariaDB
                          |
                          v
                  PHP Analytics API
                          |
                          v
                  Analytics Dashboard
                          |
             Báo cáo, biểu đồ và CSV
```

### Luồng hoạt động

1. Người dùng truy cập hoặc thao tác trên Website A hoặc Website B.
2. JavaScript SDK phát sinh sự kiện theo cấu hình được cho phép.
3. SDK gửi dữ liệu sự kiện đến PHP API.
4. Backend kiểm tra website, phiên truy cập, loại sự kiện, thời điểm và tính hợp lệ của dữ liệu.
5. Hệ thống xử lý sự kiện trùng hoặc xung đột trước khi lưu.
6. Sự kiện hợp lệ được lưu trong database.
7. API báo cáo truy vấn và tổng hợp dữ liệu.
8. Dashboard hiển thị kết quả theo website, khoảng thời gian, trang và loại sự kiện.

Các số liệu trên dashboard phải được lấy từ dữ liệu thực tế của hệ thống, không sử dụng số liệu hardcode để thay thế kết quả thống kê.

## 5. Chức năng chính

### 5.1. Quản lý website

- Đăng ký website thử nghiệm.
- Cấu hình trạng thái website.
- Cấu hình múi giờ.
- Quản lý các sự kiện được phép thu thập.
- Phân tách dữ liệu theo từng website.

### 5.2. Thu thập sự kiện

JavaScript Tracking SDK hỗ trợ các loại sự kiện:

| Sự kiện | Ý nghĩa |
|---|---|
| `page_view` | Người dùng xem trang |
| `button_click` | Người dùng bấm nút được cấu hình |
| `download_start` | Bắt đầu thao tác tải tài liệu |
| `download_complete` | Hoàn tất thao tác tải tài liệu theo điều kiện đã định nghĩa |

SDK cần tạo mã sự kiện duy nhất, quản lý phiên truy cập, gửi dữ liệu đến API và xử lý việc gửi lại khi có lỗi mạng.

Hệ thống không thu thập nội dung trường nhập, mật khẩu hoặc địa chỉ IP đầy đủ.

### 5.3. Tiếp nhận và lưu trữ dữ liệu

PHP API chịu trách nhiệm:

- Kiểm tra request và dữ liệu JSON.
- Xác định website tương ứng.
- Kiểm tra định nghĩa sự kiện và phiên truy cập.
- Kiểm tra thời điểm sự kiện.
- Lưu dữ liệu hợp lệ vào database.
- Giới hạn request và ghi nhận lỗi tiếp nhận.
- Xử lý an toàn các request đồng thời.

### 5.4. Chống đếm trùng

Hệ thống áp dụng quy tắc chống trùng theo website và mã sự kiện.

- Cùng website, cùng mã sự kiện và cùng nội dung: không tạo thêm bản ghi.
- Cùng website, cùng mã sự kiện nhưng khác nội dung: từ chối ghi đè và báo xung đột.
- Mã sự kiện mới, nội dung giống sự kiện trước: vẫn được tính là sự kiện mới nếu hợp lệ.

Mục tiêu là bảo đảm việc retry không làm tăng sai số liệu thống kê.

### 5.5. Dashboard và báo cáo

Dashboard cung cấp các chỉ số:

- Lượt xem trang.
- Số lần thao tác.
- Số phiên có hoạt động.
- Số lần hoàn tất tải tài liệu.
- Số lỗi tiếp nhận.
- Xu hướng truy cập theo ngày.
- Các trang phổ biến.
- Nhật ký sự kiện gần đây.

Người dùng có thể lọc theo website, khoảng thời gian và loại dữ liệu phù hợp.

### 5.6. Phân tích chuỗi thao tác

Hệ thống hỗ trợ phân tích chuỗi thao tác mẫu:

```text
page_view
    |
    v
download_start
    |
    v
download_complete
```

Một chuỗi chỉ được tính là hoàn tất khi đáp ứng các điều kiện đã định nghĩa về thứ tự, website, phiên và tính hợp lệ của sự kiện.

Báo cáo cần phân biệt chuỗi hoàn tất với chuỗi còn thiếu bước hoặc sai thứ tự.

### 5.7. Phân quyền người dùng

Hệ thống có hai vai trò:

**Admin**
- Quản lý website và định nghĩa sự kiện.
- Quản lý tài khoản.
- Cấp và thu hồi quyền truy cập.
- Xem báo cáo và nhật ký.
- Quản lý dữ liệu thử nghiệm.

**Member**
- Xem báo cáo của website được cấp quyền.
- Lọc dữ liệu và xem nhật ký trong phạm vi được phép.
- Xuất báo cáo nếu được cấp quyền.

Quyền truy cập phải được kiểm tra tại backend, không chỉ dựa vào giao diện.

### 5.8. Kiểm thử và xuất báo cáo

- Sinh dữ liệu thử nghiệm có kết quả mong đợi.
- Kiểm thử chống đếm trùng.
- Kiểm thử sự kiện qua ngày và sự kiện đến muộn.
- Kiểm thử chuỗi thao tác.
- Kiểm thử phân tách dữ liệu website.
- Kiểm thử quyền truy cập.
- Xuất báo cáo CSV.

## 6. Website thử nghiệm

Dự án sử dụng hai website:

**Website A — Cửa hàng mẫu**

Dùng để kiểm tra lượt xem trang, thao tác trên sản phẩm, tải tài liệu và các luồng thao tác được cấu hình.

**Website B — Giới thiệu dịch vụ mẫu**

Dùng để kiểm tra các sự kiện trên một website khác và xác minh rằng dữ liệu không bị trộn lẫn giữa hai website.

Mỗi website có sáu trang theo phạm vi đề tài. Cả hai đều phải sử dụng đúng cấu hình tracking của mình.

## 7. Cấu trúc database dự kiến

Các bảng chính:

| Bảng | Chức năng |
|---|---|
| `websites` | Quản lý website |
| `users` | Quản lý tài khoản và vai trò |
| `website_permissions` | Quản lý quyền theo website |
| `event_definitions` | Khai báo sự kiện được phép |
| `sessions` | Quản lý phiên truy cập giả danh |
| `events` | Lưu sự kiện hợp lệ |
| `event_ingest_errors` | Lưu thông tin lỗi tiếp nhận có giới hạn |
| `report_queries` | Lưu lịch sử truy vấn báo cáo khi cần |

Thiết kế database cần có khóa chính, khóa ngoại, unique constraints và index phù hợp để bảo đảm tính nhất quán và hỗ trợ truy vấn báo cáo.

## 8. Cài đặt và chạy trên XAMPP

### Yêu cầu

- Windows.
- XAMPP có Apache, PHP và MySQL/MariaDB.
- Visual Studio Code.
- Git nếu cần quản lý mã nguồn.

### Bước 1: Đặt dự án

Đặt thư mục dự án tại:

```text
C:\xampp\htdocs\website-analytics
```

### Bước 2: Khởi động XAMPP

Mở XAMPP Control Panel và khởi động:

- Apache
- MySQL

Kiểm tra cả hai dịch vụ đang chạy trước khi tiếp tục.

### Bước 3: Tạo database

Mở:

```text
http://localhost/phpmyadmin
```

Tạo database `website_analytics`, sau đó nhập các file SQL theo hướng dẫn cài đặt của dự án:

- `database/schema.sql`
- `database/seed.sql`

Không chạy các lệnh xóa database hiện có nếu chưa xác nhận dữ liệu cần giữ.

### Bước 4: Cấu hình kết nối

Kiểm tra file cấu hình database và cập nhật thông tin kết nối phù hợp với môi trường XAMPP thực tế.

Không đưa mật khẩu hoặc thông tin bí mật vào repository công khai.

### Bước 5: Mở ứng dụng

Truy cập URL tương ứng với cấu trúc dự án, ví dụ:

```text
http://localhost/website-analytics/
```

Nếu dự án sử dụng thư mục public hoặc virtual host riêng, hãy làm theo hướng dẫn cài đặt thực tế của phiên bản đó.

### Bước 6: Kiểm tra tracking

1. Mở Website A hoặc Website B.
2. Thực hiện một thao tác đã được cấu hình.
3. Kiểm tra request gửi đến PHP API.
4. Kiểm tra bản ghi trong bảng `events`.
5. Mở dashboard và xác nhận số liệu được truy vấn từ dữ liệu thực.

Các tài khoản mẫu, nếu có, phải được ghi rõ trong hướng dẫn local và không được dùng với mật khẩu mặc định trên hệ thống công khai.

## 9. Kiểm thử

Các nhóm kiểm thử bắt buộc:

- Kiểm tra lượt xem và số lần thao tác.
- Kiểm tra gửi lại cùng mã sự kiện.
- Kiểm tra xung đột cùng mã nhưng khác nội dung.
- Kiểm tra mã sự kiện mới có nội dung giống nhau.
- Kiểm tra sự kiện qua nửa đêm.
- Kiểm tra sự kiện đến muộn.
- Kiểm tra chuỗi thao tác hoàn tất, thiếu bước hoặc sai thứ tự.
- Kiểm tra dữ liệu không bị trộn giữa hai website.
- Kiểm tra phân quyền Admin và Member.
- Kiểm tra xuất CSV và đối chiếu dữ liệu nguồn.

Kết quả kiểm thử phải được xác nhận bằng việc chạy thực tế trên mã nguồn hiện tại.

## 10. An toàn và quyền riêng tư

Dự án chỉ thu thập dữ liệu trên website thử nghiệm được phép theo dõi.

Các nguyên tắc:

- Không thu thập mật khẩu hoặc nội dung trường nhập.
- Không lưu địa chỉ IP đầy đủ.
- Không sử dụng fingerprinting.
- Không theo dõi chéo website.
- Sử dụng prepared statements.
- Kiểm tra quyền ở backend.
- Giới hạn kích thước request và tần suất gửi.
- Có chính sách lưu trữ và xóa dữ liệu thử.
- Không đưa thông tin bí mật lên GitHub.

Session ID chỉ dùng để nhóm các sự kiện trong phiên, không được coi là định danh chắc chắn của một người thật.

## 11. Định hướng phát triển

Các hướng cải thiện tiếp theo:

- Hoàn thiện giao diện dashboard responsive.
- Tối ưu truy vấn báo cáo.
- Mở rộng bộ kiểm thử tự động.
- Cải thiện tài liệu API.
- Bổ sung giám sát lỗi và hướng dẫn khôi phục.
- Chuẩn hóa quy trình triển khai trên môi trường khác.

## 12. Trạng thái dự án

Dự án được phát triển phục vụ mục đích học tập và nghiên cứu.

Trạng thái triển khai, kết quả kiểm thử và danh sách chức năng đã hoàn thành cần được cập nhật dựa trên mã nguồn và kết quả chạy thực tế.

Không coi một chức năng là hoàn thành chỉ vì đã có giao diện hoặc file mã nguồn.

## 13. Đóng góp

Nếu muốn đóng góp cho dự án:

1. Fork repository.
2. Tạo branch cho thay đổi.
3. Thực hiện chỉnh sửa và kiểm thử.
4. Commit với nội dung rõ ràng.
5. Tạo Pull Request để xem xét thay đổi.

## 14. Giấy phép

Giấy phép của dự án sẽ được xác định trong file `LICENSE` ở thư mục gốc. Các thư viện bên thứ ba phải tuân thủ giấy phép tương ứng.

---

**Tên đề tài:** Xây dựng công cụ thống kê lượt truy cập và thao tác trên website.

**Môi trường phát triển:** XAMPP, PHP, MySQL/MariaDB, JavaScript và GitHub.