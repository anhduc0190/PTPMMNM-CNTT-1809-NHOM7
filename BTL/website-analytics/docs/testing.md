# TÀI LIỆU KIỂM THỬ VÀ ĐỐI CHIẾU RUBRIC ĐỒ ÁN

**Học phần:** Phát triển phần mềm mã nguồn mở  
**Đề tài 17:** Xây dựng công cụ thống kê lượt truy cập và thao tác trên website  

---

## 1. Kiến Trúc Bộ Dữ Liệu Kiểm Thử Deterministic (225 Requests)

Hệ thống được trang bị bộ sinh dữ liệu kiểm thử tự động có đáp án xác định (Ground Truth) tại file [`scripts/generate-test-data.php`](file:///d:/btl%20PMP/PTPMMNM-CNTT-1809-NHOM7/BTL/website-analytics/scripts/generate-test-data.php):

| Phân loại | Số lượng request | Kết quả kỳ vọng | Cơ chế kiểm tra |
| :--- | :--- | :--- | :--- |
| **Website A — Funnel Hoàn Tất** | 45 requests (15 sessions $\times$ 3 bước) | 45 sự kiện hợp lệ | Đủ 3 bước: `page_view` $\to$ `download_start` $\to$ `download_complete` |
| **Website A — Funnel Bỏ Dở** | 20 requests (10 sessions $\times$ 2 bước) | 20 sự kiện hợp lệ | Chỉ có 2 bước đầu, thiếu `download_complete` |
| **Website A — Duyệt Trang & Click** | 45 requests (15 sessions $\times$ 3 bước) | 45 sự kiện hợp lệ | `page_view` tại `/`, `page_view` tại `/products.html`, `button_click` |
| **Website A — Nửa Đêm** | 10 requests (5 sessions $\times$ 2 bước) | 10 sự kiện hợp lệ | Bước 1 lúc 23:58 (Hôm qua), Bước 2 lúc 00:03 (Hôm nay) |
| **Website B — Funnel Hoàn Tất** | 30 requests (10 sessions $\times$ 3 bước) | 30 sự kiện hợp lệ | Chuỗi 3 bước cách ly độc lập trên Website B |
| **Website B — Funnel Bỏ Dở** | 10 requests (5 sessions $\times$ 2 bước) | 10 sự kiện hợp lệ | 2 bước đầu trên Website B |
| **Website B — Duyệt & Tư Vấn** | 20 requests (10 sessions $\times$ 2 bước) | 20 sự kiện hợp lệ | `page_view` và `button_click` trên Website B |
| **Gửi trùng mã cũ (Case A)** | 10 requests | 0 sự kiện thêm mới | Trả về HTTP 200 `duplicate_idempotent`, không tăng đếm |
| **Xung đột mã cũ (Case B)** | 5 requests | 0 sự kiện thêm mới | Trả về HTTP 409 `PAYLOAD_CONFLICT`, ghi vào `event_ingest_errors` |
| **Mã mới cùng nội dung (Case C)** | 5 requests | 5 sự kiện mới | Trả về HTTP 201 `created` |
| **Sự kiện đến muộn (> 24h)** | 5 requests | 0 sự kiện thêm mới | Trả về HTTP 422 `LATE_EVENT_EXCEEDED`, ghi log lỗi |
| **Khoá tracking không hợp lệ** | 5 requests | 0 sự kiện thêm mới | Trả về HTTP 404 `WEBSITE_NOT_FOUND` |
| **Sự kiện chưa khai báo** | 5 requests | 0 sự kiện thêm mới | Trả về HTTP 400 `EVENT_NOT_DEFINED`, ghi log lỗi |
| **Funnel sai thứ tự** | 10 requests (5 sessions $\times$ 2 bước) | 10 sự kiện lưu CSDL | `download_complete` xảy ra trước `download_start` $\to$ Funnel = 0 |
| **TỔNG CỘNG** | **225 requests** | **195 sự kiện lưu CSDL** | **10 trùng, 5 xung đột, 15 từ chối** |

---

## 2. Kết Quả Chạy Thực Tế 12 Bài Test Nghiệm Thu Theo Rubric

Chạy lệnh kiểm thử:
```powershell
& "C:\xampp\php\php.exe" scripts/run-tests.php
```

### Kết quả chi tiết (12/12 PASS — Tỷ lệ đạt: 100.0%):

1. **Test 01: Tổng hợp lượt xem ($\ge 10$) và bấm nút ($\ge 4$) cho kết quả chính xác**  
   $\to$ **[PASS]** Thực tế: 62 Views, 20 Clicks trên Website A.
2. **Test 02: Chống đếm trùng Case A: Gửi lại cùng event_id & payload không tăng số đếm**  
   $\to$ **[PASS]** Thực tế: 10/10 duplicate requests được nhận diện chuẩn, không tăng dòng CSDL.
3. **Test 03: Phát hiện xung đột Case B: Cùng event_id khác payload báo lỗi 409 & ghi log**  
   $\to$ **[PASS]** Thực tế: 5/5 bản ghi `PAYLOAD_CONFLICT` được lưu vào `event_ingest_errors`.
4. **Test 04: Chấp nhận Case C: Event ID mới với payload giống vẫn được tính hợp lệ**  
   $\to$ **[PASS]** Thực tế: Đã chấp nhận 5 sự kiện mới và cấp phát ID riêng biệt.
5. **Test 05: Xử lý múi giờ qua nửa đêm: Sự kiện 23:58 và 00:03 được nhóm đúng ngày**  
   $\to$ **[PASS]** Thực tế: Hôm qua: 5 sự kiện, Hôm nay: 130 sự kiện theo múi giờ `Asia/Ho_Chi_Minh`.
6. **Test 06: Chính sách sự kiện trễ: Từ chối sự kiện > 24 giờ và ghi log LATE_EVENT_EXCEEDED**  
   $\to$ **[PASS]** Thực tế: 5/5 sự kiện trễ bị từ chối với HTTP 422.
7. **Test 07: Phân tích Funnel: Chuỗi page_view $\to$ download_start $\to$ download_complete**  
   $\to$ **[PASS]** Thực tế: Site A có đúng 15 phiên hoàn tất toàn bộ chuỗi.
8. **Test 08: Chống sai chuỗi: Chuỗi thiếu bước hoặc ngược thứ tự bị loại bỏ khỏi Hoàn tất**  
   $\to$ **[PASS]** Thực tế: 10 phiên thiếu bước 3 và 5 phiên ngược thứ tự bị loại bỏ chính xác.
9. **Test 09: Cách ly dữ liệu đa website: Website A và Website B hoàn toàn tách biệt**  
   $\to$ **[PASS]** Thực tế: 0 phiên trùng chéo; Site A có 135 sự kiện, Site B có 60 sự kiện.
10. **Test 10: Phân quyền RBAC: Member chỉ xem được website được cấp (Site 1), cấm Site 2**  
    $\to$ **[PASS]** Thực tế: Member truy cập Site 1 thành công; bị chặn 403 Forbidden khi truy cập Site 2.
11. **Test 11: Bảo vệ quyền riêng tư: Tuyệt đối không lưu mật khẩu, input text hay dữ liệu nhạy cảm**  
    $\to$ **[PASS]** Thực tế: 0 trường nhạy cảm xuất hiện trong bảng `events` và `event_ingest_errors`.
12. **Test 12: Kiểm thử tích hợp End-to-End: Luồng hoàn chỉnh từ Client SDK đến Dashboard**  
    $\to$ **[PASS]** Thực tế: Cả 8 bảng CSDL, API, SDK và Dashboard hoạt động đồng bộ 100%.
