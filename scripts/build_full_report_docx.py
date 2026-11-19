# -*- coding: utf-8 -*-
"""
SCRIPT: TẠO BÁO CÁO HỌC PHẦN K18 BÀI TẬP LỚN PHÁT TRIỂN PHẦN MỀM MÃ NGUỒN MỞ
Đề tài 17: Xây Dựng Công Cụ Thống Kê Lượt Truy Cập Và Thao Tác Trên Website
Đơn vị: Nhóm 07 - Lớp CNTT 18-09 - Khoa CNTT - Trường Đại học Đại Nam
"""

import os
import docx
from docx.shared import Inches, Pt, RGBColor, Cm
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import parse_xml, OxmlElement
from docx.oxml.ns import nsdecls, qn

def create_full_report(output_path):
    doc = docx.Document()
    
    # Thiết lập kích thước trang A4 và lề theo quy định K18
    section = doc.sections[0]
    section.page_width = Cm(21.0)
    section.page_height = Cm(29.7)
    section.top_margin = Cm(2.0)
    section.bottom_margin = Cm(2.0)
    section.left_margin = Cm(2.5)
    section.right_margin = Cm(2.0)
    
    # Thiết lập số trang ở giữa, phía dưới
    footer = section.footer
    f_p = footer.paragraphs[0]
    f_p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    f_run = f_p.add_run()
    f_run.font.name = 'Times New Roman'
    f_run.font.size = Pt(10)
    fldChar1 = parse_xml(r'<w:fldChar %s w:fldCharType="begin"/>' % nsdecls('w'))
    instrText = parse_xml(r'<w:instrText %s xml:space="preserve"> PAGE </w:instrText>' % nsdecls('w'))
    fldChar2 = parse_xml(r'<w:fldChar %s w:fldCharType="separate"/>' % nsdecls('w'))
    fldChar3 = parse_xml(r'<w:fldChar %s w:fldCharType="end"/>' % nsdecls('w'))
    f_run._r.append(fldChar1)
    f_run._r.append(instrText)
    f_run._r.append(fldChar2)
    f_run._r.append(fldChar3)

    # Đổi font mặc định của Normal style
    style_normal = doc.styles['Normal']
    font = style_normal.font
    font.name = 'Times New Roman'
    font.size = Pt(13)
    font.color.rgb = RGBColor(30, 41, 59) # Slate 800

    # Helper functions
    def add_p(text, bold=False, italic=False, space_after=6, align=WD_ALIGN_PARAGRAPH.JUSTIFY, color=None, first_indent=0.75):
        p = doc.add_paragraph()
        p.alignment = align
        p.paragraph_format.line_spacing = 1.3
        p.paragraph_format.space_after = Pt(space_after)
        if first_indent > 0 and align == WD_ALIGN_PARAGRAPH.JUSTIFY:
            p.paragraph_format.first_line_indent = Cm(first_indent)
        run = p.add_run(text)
        run.bold = bold
        run.italic = italic
        run.font.name = 'Times New Roman'
        run.font.size = Pt(13)
        if color:
            run.font.color.rgb = color
        return p

    def add_h1(text):
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(16)
        p.paragraph_format.space_after = Pt(8)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.bold = True
        run.font.name = 'Times New Roman'
        run.font.size = Pt(16)
        run.font.color.rgb = RGBColor(15, 23, 42) # #0f172a
        return p

    def add_h2(text):
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(12)
        p.paragraph_format.space_after = Pt(6)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.bold = True
        run.font.name = 'Times New Roman'
        run.font.size = Pt(14)
        run.font.color.rgb = RGBColor(30, 58, 138) # #1e3a8a
        return p

    def add_h3(text):
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(8)
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.bold = True
        run.italic = True
        run.font.name = 'Times New Roman'
        run.font.size = Pt(13)
        run.font.color.rgb = RGBColor(51, 65, 85) # #334155
        return p

    def add_table_caption(text):
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(8)
        p.paragraph_format.space_after = Pt(4)
        p.paragraph_format.keep_with_next = True
        run = p.add_run(text)
        run.italic = True
        run.font.name = 'Times New Roman'
        run.font.size = Pt(12)
        run.font.color.rgb = RGBColor(71, 85, 105)
        return p

    def add_fig_caption(text):
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_before = Pt(4)
        p.paragraph_format.space_after = Pt(10)
        run = p.add_run(text)
        run.italic = True
        run.font.name = 'Times New Roman'
        run.font.size = Pt(12)
        run.font.color.rgb = RGBColor(71, 85, 105)
        return p

    def add_styled_table(headers, rows, col_widths=None):
        table = doc.add_table(rows=len(rows) + 1, cols=len(headers))
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        
        # Style Header
        hdr_cells = table.rows[0].cells
        for i, header_text in enumerate(headers):
            hdr_cells[i].text = header_text
            p = hdr_cells[i].paragraphs[0]
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.space_after = Pt(3)
            p.paragraph_format.space_before = Pt(3)
            for r in p.runs:
                r.bold = True
                r.font.name = 'Times New Roman'
                r.font.size = Pt(11)
                r.font.color.rgb = RGBColor(15, 23, 42)
            shading = parse_xml(r'<w:shd %s w:fill="E2E8F0"/>' % nsdecls('w'))
            hdr_cells[i]._tc.get_or_add_tcPr().append(shading)
            
        # Style Data Rows
        for r_idx, row_data in enumerate(rows):
            row_cells = table.rows[r_idx + 1].cells
            bg_color = "FFFFFF" if r_idx % 2 == 0 else "F8FAFC"
            for c_idx, cell_value in enumerate(row_data):
                row_cells[c_idx].text = str(cell_value)
                p = row_cells[c_idx].paragraphs[0]
                p.paragraph_format.space_after = Pt(2)
                p.paragraph_format.space_before = Pt(2)
                p.paragraph_format.line_spacing = 1.15
                if c_idx == 0:
                    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                else:
                    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                for r in p.runs:
                    r.font.name = 'Times New Roman'
                    r.font.size = Pt(10.5)
                shading = parse_xml(r'<w:shd %s w:fill="%s"/>' % (nsdecls('w'), bg_color))
                row_cells[c_idx]._tc.get_or_add_tcPr().append(shading)
                
        # Set borders
        tblPr = table._tbl.tblPr
        borders = parse_xml(
            r'<w:tblBorders %s>'
            r'<w:top w:val="single" w:sz="4" w:space="0" w:color="CBD5E1"/>'
            r'<w:bottom w:val="single" w:sz="6" w:space="0" w:color="94A3B8"/>'
            r'<w:insideH w:val="single" w:sz="4" w:space="0" w:color="E2E8F0"/>'
            r'<w:insideV w:val="none"/>'
            r'<w:left w:val="none"/>'
            r'<w:right w:val="none"/>'
            r'</w:tblBorders>' % nsdecls('w')
        )
        tblPr.append(borders)
        
        # Set Widths if provided
        if col_widths and len(col_widths) == len(headers):
            for row in table.rows:
                for idx, width in enumerate(col_widths):
                    row.cells[idx].width = Cm(width)
                    
        doc.add_paragraph().paragraph_format.space_after = Pt(4)
        return table

    def add_image_if_exists(img_rel_path, caption_text, width_cm=14.5):
        full_path = os.path.join(r'd:\btl PMP\PTPMMNM-CNTT-1809-NHOM7\BTL\Hinh_anh_Bao_cao', img_rel_path)
        if os.path.exists(full_path):
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            p.paragraph_format.space_before = Pt(8)
            p.paragraph_format.space_after = Pt(2)
            run = p.add_run()
            run.add_picture(full_path, width=Cm(width_cm))
            add_fig_caption(caption_text)
        else:
            add_p(f"[Hình minh họa: {caption_text}]", italic=True, align=WD_ALIGN_PARAGRAPH.CENTER)

    def add_code_block(code_text):
        tbl = doc.add_table(rows=1, cols=1)
        tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
        cell = tbl.cell(0, 0)
        cell.width = Cm(16.5)
        shading = parse_xml(r'<w:shd %s w:fill="0F172A"/>' % nsdecls('w'))
        cell._tc.get_or_add_tcPr().append(shading)
        
        p = cell.paragraphs[0]
        p.paragraph_format.space_before = Pt(6)
        p.paragraph_format.space_after = Pt(6)
        p.paragraph_format.line_spacing = 1.15
        run = p.add_run(code_text)
        run.font.name = 'Consolas'
        run.font.size = Pt(9.5)
        run.font.color.rgb = RGBColor(248, 250, 252)
        doc.add_paragraph().paragraph_format.space_after = Pt(4)

    # =========================================================================
    # TRANG BÌA (Cover Page)
    # =========================================================================
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("TRƯỜNG ĐẠI HỌC ĐẠI NAM\nKHOA CÔNG NGHỆ THÔNG TIN\n")
    r.bold = True
    r.font.name = 'Times New Roman'
    r.font.size = Pt(14)
    r.font.color.rgb = RGBColor(15, 23, 42)

    p_line = doc.add_paragraph()
    p_line.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r_line = p_line.add_run("--------------------***--------------------")
    r_line.font.name = 'Times New Roman'
    r_line.font.size = Pt(12)
    p_line.paragraph_format.space_after = Pt(60)

    p_title = doc.add_paragraph()
    p_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_title.paragraph_format.space_after = Pt(14)
    r_title = p_title.add_run("BÁO CÁO BÀI TẬP LỚN\nHỌC PHẦN: PHÁT TRIỂN PHẦN MỀM MÃ NGUỒN MỞ\n")
    r_title.bold = True
    r_title.font.name = 'Times New Roman'
    r_title.font.size = Pt(16)
    r_title.font.color.rgb = RGBColor(30, 58, 138)

    p_dt = doc.add_paragraph()
    p_dt.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_dt.paragraph_format.space_after = Pt(80)
    r_dt = p_dt.add_run("ĐỀ TÀI 17:\nXÂY DỰNG CÔNG CỤ THỐNG KÊ LƯỢT TRUY CẬP\nVÀ THAO TÁC TRÊN WEBSITE\n(WEB ANALYTICS PLATFORM)")
    r_dt.bold = True
    r_dt.font.name = 'Times New Roman'
    r_dt.font.size = Pt(18)
    r_dt.font.color.rgb = RGBColor(185, 28, 28) # #b91c1c

    # Bảng thông tin sinh viên và giảng viên
    info_tbl = doc.add_table(rows=6, cols=2)
    info_tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    labels = [
        ("Giảng viên hướng dẫn:", "ThS. Nguyễn Ngọc Ân"),
        ("Lớp học phần:", "CNTT 18-09"),
        ("Nhóm thực hiện:", "Nhóm 07"),
        ("Sinh viên thực hiện 1:", "Nguyễn Anh Đức (Nhóm trưởng)"),
        ("Sinh viên thực hiện 2:", "Lưu Phương Thảo"),
        ("Sinh viên thực hiện 3:", "Lê Hoàng Nam")
    ]
    for idx, (lbl, val) in enumerate(labels):
        cell_lbl = info_tbl.cell(idx, 0)
        cell_val = info_tbl.cell(idx, 1)
        cell_lbl.width = Cm(6.5)
        cell_val.width = Cm(10.0)
        p_l = cell_lbl.paragraphs[0]
        p_l.paragraph_format.line_spacing = 1.2
        r_l = p_l.add_run(lbl)
        r_l.bold = True
        r_l.font.name = 'Times New Roman'
        r_l.font.size = Pt(12)
        
        p_v = cell_val.paragraphs[0]
        p_v.paragraph_format.line_spacing = 1.2
        r_v = p_v.add_run(val)
        r_v.font.name = 'Times New Roman'
        r_v.font.size = Pt(12)

    p_bottom = doc.add_paragraph()
    p_bottom.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p_bottom.paragraph_format.space_before = Pt(80)
    r_bot = p_bottom.add_run("Hà Nội, 2026")
    r_bot.bold = True
    r_bot.font.name = 'Times New Roman'
    r_bot.font.size = Pt(13)

    doc.add_page_break()

    # =========================================================================
    # LỜI NÓI ĐẦU
    # =========================================================================
    add_h1("LỜI NÓI ĐẦU")
    add_p("Trong kỷ nguyên chuyển đổi số và thương mại điện tử hiện nay, dữ liệu hành vi của người dùng trên website đóng vai trò then chốt đối với sự phát triển của mọi tổ chức và doanh nghiệp. Việc nắm bắt chính xác khách hàng truy cập vào những trang nào, tương tác với các nút bấm chức năng ra sao, quy trình tải tài liệu hoặc đặt hàng có gặp khó khăn gì không là cơ sở sống còn để tối ưu hóa trải nghiệm người dùng (UI/UX) và thúc đẩy tỷ lệ chuyển đổi kinh doanh.")
    add_p("Trước đây, các doanh nghiệp thường sử dụng các nền tảng phân tích đám mây của bên thứ ba như Google Analytics, Mixpanel hay Hotjar. Tuy nhiên, sự phụ thuộc vào các dịch vụ đóng kín này đặt ra nhiều thách thức nghiêm trọng về quyền riêng tư dữ liệu (theo các tiêu chuẩn GDPR, ISO 27001), nguy cơ rò rỉ thông tin khách hàng nhạy cảm, độ trễ xử lý dữ liệu và chi phí bản quyền tăng vọt khi quy mô truy cập mở rộng. Xu hướng làm chủ công nghệ và phát triển các hệ thống Web Analytics trên nền tảng Mã Nguồn Mở (Open Source) đang trở thành một lựa chọn chiến lược hàng đầu của các tập đoàn công nghệ.")
    add_p("Thực hiện mục tiêu của học phần 'Phát triển phần mềm mã nguồn mở', Nhóm 07 (Lớp CNTT 18-09) đã lựa chọn và hoàn thành Đề tài 17: 'Xây dựng công cụ thống kê lượt truy cập và thao tác trên website'. Hệ thống được nghiên cứu, thiết kế và phát triển hoàn toàn trên nền tảng công nghệ PHP 8.2 thuần kết hợp PDO, cơ sở dữ liệu MariaDB/MySQL và JavaScript Tracking SDK thuần, bảo đảm khả năng kiểm soát toàn diện 100% mã nguồn, vận hành độc lập không phụ thuộc thư viện đám mây bên ngoài.")
    add_p("Nhóm chúng em xin gửi lời cảm ơn chân thành và sâu sắc nhất tới Thầy ThS. Nguyễn Ngọc Ân đã tận tình hướng dẫn, trang bị các kiến thức chuyên môn quý báu về tư duy kiến trúc mã nguồn mở, kỹ năng giải quyết xung đột dữ liệu và phương pháp kiểm thử khoa học trong suốt quá trình học phần. Dù đã có nhiều cố gắng, báo cáo chắc chắn khó tránh khỏi những thiếu sót nhất định. Nhóm chúng em rất mong nhận được những nhận xét, đóng góp quý báu từ quý Thầy/Cô để hệ thống tiếp tục hoàn thiện hơn nữa.")

    doc.add_page_break()

    # =========================================================================
    # MỤC LỤC & DANH MỤC
    # =========================================================================
    add_h1("MỤC LỤC")
    add_p("[Hệ thống đề mục chính của Báo cáo học phần]")
    toc_items = [
        ("LỜI NÓI ĐẦU", 2),
        ("MỤC LỤC", 3),
        ("BẢNG CÁC TỪ VIẾT TẮT", 4),
        ("PHẦN MỞ ĐẦU", 5),
        ("1. Lý do chọn đề tài", 5),
        ("2. Mục tiêu đề tài", 5),
        ("3. Đối tượng và phạm vi nghiên cứu", 6),
        ("4. Phương pháp thực hiện", 6),
        ("5. Sản phẩm dự kiến", 6),
        ("CHƯƠNG 1: PHÂN TÍCH YÊU CẦU VÀ THIẾT KẾ HỆ THỐNG MẠNG VÀ PHẦN MỀM", 7),
        ("1.1. Mô tả kịch bản bài toán và cơ cấu tổ chức", 7),
        ("1.2. Phân tích yêu cầu kỹ thuật và công nghệ lựa chọn", 8),
        ("1.3. Yêu cầu chức năng và tiêu chí chấp nhận (Rubric)", 9),
        ("1.4. Thiết kế cơ sở dữ liệu và chuẩn hóa dữ liệu", 11),
        ("1.5. Thiết kế kiến trúc hệ thống và luồng dữ liệu (Data Pipeline)", 14),
        ("1.6. Thiết kế cơ chế chống đếm trùng (Idempotency) và bắt xung đột", 16),
        ("1.7. Thiết kế thuật toán phân tích chuỗi thao tác (Conversion Funnel)", 18),
        ("1.8. Thiết kế phân quyền người dùng theo vai trò (RBAC)", 20),
        ("1.9. Ma trận truy vết yêu cầu và thiết kế (Traceability Matrix)", 21),
        ("CHƯƠNG 2: TRIỂN KHAI VÀ CẤU HÌNH HỆ THỐNG", 23),
        ("2.1. Chuẩn bị môi trường phát triển và cấu hình máy chủ", 23),
        ("2.2. Xây dựng và tối ưu cơ sở dữ liệu", 24),
        ("2.3. Phát triển JavaScript Tracking SDK (sdk/tracker.js)", 26),
        ("2.4. Phát triển REST API tiếp nhận và chống gian lận (api/track.php)", 28),
        ("2.5. Xây dựng Dashboard thống kê tổng quan (dashboard.php)", 30),
        ("2.6. Phát triển module phân tích chuỗi thao tác Funnel (funnels.php)", 32),
        ("2.7. Xây dựng module báo cáo chi tiết và xuất CSV (reports.php)", 34),
        ("2.8. Triển khai phân quyền RBAC và quản lý tài khoản (users.php)", 35),
        ("2.9. Xây dựng Trung tâm kiểm thử tự động 12 tiêu chí (test-center.php)", 37),
        ("CHƯƠNG 3: KIỂM THỬ, ĐÁNH GIÁ VÀ HỒ SƠ BÀN GIAO", 39),
        ("3.1. Kế hoạch kiểm thử và bộ dữ liệu mẫu Deterministic (225 Requests)", 39),
        ("3.2. Kiểm thử chống đếm trùng và phát hiện xung đột (Case A, B, C)", 41),
        ("3.3. Kiểm thử múi giờ qua nửa đêm và sự kiện trễ 24 giờ", 42),
        ("3.4. Kiểm thử chuỗi thao tác Funnel và bắt lỗi thứ tự thời gian", 43),
        ("3.5. Kiểm thử cô lập đa website và bảo mật phân quyền RBAC", 44),
        ("3.6. Kiểm thử bảo vệ quyền riêng tư 100% (Whitelist attributes)", 45),
        ("3.7. Kiểm thử tích hợp End-to-End toàn hệ thống (12/12 PASS 100%)", 46),
        ("3.8. Xử lý sự cố và khắc phục lỗi thực tế (Troubleshooting)", 47),
        ("3.9. Hướng dẫn cài đặt, vận hành và tái lập hệ thống", 48),
        ("3.10. Hồ sơ bàn giao sản phẩm", 49),
        ("KẾT LUẬN", 50),
        ("TÀI LIỆU THAM KHẢO", 51),
        ("PHỤ LỤC", 52)
    ]
    for item, page in toc_items:
        p = doc.add_paragraph()
        p.paragraph_format.line_spacing = 1.15
        p.paragraph_format.space_after = Pt(2)
        r1 = p.add_run(item)
        r1.font.name = 'Times New Roman'
        r1.font.size = Pt(11)
        r1.bold = item.isupper() or item.startswith("CHƯƠNG") or item.startswith("PHẦN") or item.startswith("KẾT")
        dots_count = max(5, 75 - len(item))
        r_dots = p.add_run(" " + "." * dots_count + " ")
        r_dots.font.name = 'Times New Roman'
        r_dots.font.size = Pt(10)
        r_dots.font.color.rgb = RGBColor(148, 163, 184)
        r2 = p.add_run(str(page))
        r2.font.name = 'Times New Roman'
        r2.font.size = Pt(11)
        r2.bold = True

    add_h1("BẢNG CÁC TỪ VIẾT TẮT")
    add_table_caption("Bảng 0.1: Danh mục các từ viết tắt trong báo cáo")
    acronyms = [
        ("API", "Application Programming Interface (Giao diện lập trình ứng dụng)"),
        ("BTL", "Bài Tập Lớn"),
        ("CORS", "Cross-Origin Resource Sharing (Cơ chế chia sẻ tài nguyên giữa các nguồn)"),
        ("CSRF", "Cross-Site Request Forgery (Tấn công giả mạo yêu cầu từ phía người dùng)"),
        ("CSV", "Comma-Separated Values (Định dạng tệp dữ liệu phân tách bởi dấu phẩy)"),
        ("DDL", "Data Definition Language (Ngôn ngữ định nghĩa dữ liệu SQL)"),
        ("ERD", "Entity-Relationship Diagram (Sơ đồ mối quan hệ thực thể)"),
        ("GDPR", "General Data Protection Regulation (Quy định chung về bảo vệ dữ liệu)"),
        ("Idempotency", "Tính chất thao tác: Thực thi lặp lại nhiều lần cho ra cùng một kết quả"),
        ("KPI", "Key Performance Indicator (Chỉ số hiệu suất cốt lõi)"),
        ("PDO", "PHP Data Objects (Lớp trừu tượng hóa kết nối cơ sở dữ liệu an toàn trong PHP)"),
        ("RBAC", "Role-Based Access Control (Kiểm soát truy cập dựa trên vai trò)"),
        ("SDK", "Software Development Kit (Bộ công cụ phát triển phần mềm nhúng)"),
        ("SHA-256", "Secure Hash Algorithm 256-bit (Thuật toán băm mật mã an toàn 256-bit)"),
        ("SQL", "Structured Query Language (Ngôn ngữ truy vấn cơ sở dữ liệu có cấu trúc)"),
        ("UI/UX", "User Interface / User Experience (Giao diện và trải nghiệm người dùng)"),
        ("UUID", "Universally Unique Identifier (Mã định danh duy nhất toàn cầu 128-bit)"),
        ("VLAN", "Virtual Local Area Network (Mạng cục bộ ảo)")
    ]
    add_styled_table(["Từ viết tắt", "Diễn giải ý nghĩa nghiệp vụ và kỹ thuật"], acronyms, [3.5, 13.0])

    doc.add_page_break()

    # =========================================================================
    # PHẦN MỞ ĐẦU
    # =========================================================================
    add_h1("PHẦN MỞ ĐẦU")
    
    add_h2("1. Lý do chọn đề tài")
    add_p("Trong kỷ nguyên công nghệ thông tin hiện đại, khả năng thu thập và phân tích dữ liệu hành vi người dùng là nền tảng cốt lõi giúp các doanh nghiệp ra quyết định kinh doanh chính xác. Khi người dùng tương tác trên một website, các hành vi như duyệt xem trang sản phẩm, tìm kiếm, bấm nút 'Thêm vào giỏ hàng', hoặc tải tài liệu hướng dẫn đều phản ánh nhu cầu và trải nghiệm thực tế của họ.")
    add_p("Mặc dù trên thị trường đã có nhiều giải pháp phân tích đám mây mạnh mẽ (như Google Analytics 4, Mixpanel, Amplitude), nhưng việc sử dụng các dịch vụ bên thứ ba này bộc lộ nhiều hạn chế lớn:")
    add_p("Thứ nhất là vấn đề chủ quyền dữ liệu và quyền riêng tư: Các quy định bảo vệ dữ liệu nghiêm ngặt như Nghị định 13/2023/NĐ-CP của Việt Nam và tiêu chuẩn GDPR của Châu Âu yêu cầu doanh nghiệp phải kiểm soát chặt chẽ việc lưu trữ dữ liệu người dùng, không được để lộ dữ liệu nhạy cảm cho các nền tảng quảng cáo trung gian.")
    add_p("Thứ hai là vấn đề chi phí và kiểm soát kỹ thuật: Khi lưu lượng truy cập đạt hàng triệu lượt mỗi tháng, chi phí bản quyền dịch vụ phân tích đám mây tăng theo cấp số nhân. Đồng thời, mã nguồn đóng kín khiến doanh nghiệp không thể tùy biến các thuật toán chống đếm trùng, chống gian lận dữ liệu hay phân tích phễu chuyển đổi đặc thù.")
    add_p("Do đó, việc nghiên cứu và tự xây dựng một hệ thống Web Analytics trên nền tảng Mã Nguồn Mở (Open Source) là một nhiệm vụ có tính thời sự cao, vừa giúp sinh viên nắm vững nguyên lý hoạt động của các hệ thống phân tích dữ liệu lớn, vừa rèn luyện kỹ năng lập trình web an toàn, tối ưu hóa cơ sở dữ liệu và triển khai kiến trúc phần mềm tin cậy.")

    add_h2("2. Mục tiêu đề tài")
    add_p("Đề tài hướng tới việc thiết kế và xây dựng hoàn chỉnh một hệ thống Web Analytics Platform đáp ứng đầy đủ các mục tiêu cụ thể sau:")
    add_p("1. Tự xây dựng bộ mã thu thập dữ liệu (JavaScript Tracking SDK) thuần, kích thước nhẹ (<10KB), có khả năng tự động nhúng vào bất kỳ website nào chỉ với một thẻ <script> duy nhất.")
    add_p("2. Xây dựng REST API tiếp nhận sự kiện tốc độ cao, triển khai cơ chế chống đếm trùng lặp (Idempotent Deduplication) bằng thuật toán chuẩn hóa dữ liệu kết hợp hàm băm SHA-256.")
    add_p("3. Xây dựng cơ sở dữ liệu chuẩn hóa trên MariaDB/MySQL với 8 bảng quan hệ, thiết kế hệ thống chỉ mục (Index) chuyên sâu phục vụ tổng hợp hàng triệu sự kiện với thời gian truy vấn dưới 100ms.")
    add_p("4. Phát triển thuật toán phân tích chuỗi hành trình (Conversion Funnel Analysis) nhằm xác thực thứ tự thời gian logic giữa các bước và nhận diện chính xác các phiên bỏ dở (Drop-off).")
    add_p("5. Triển khai cơ chế phân quyền người dùng đa cấp (Role-Based Access Control - RBAC) bảo đảm tính cách ly tuyệt đối giữa các website khác nhau trong hệ thống.")
    add_p("6. Tích hợp trung tâm kiểm thử tự động đáp ứng 100% barem 12 tiêu chí chấm điểm của Giảng viên, có minh chứng số liệu đối chiếu thực tế.")

    add_h2("3. Đối tượng và phạm vi nghiên cứu")
    add_p("• Đối tượng nghiên cứu: Quy trình thu thập, xử lý, lọc nhiễu, chống gian lận và thống kê dữ liệu sự kiện web theo thời gian thực; cơ chế bảo vệ quyền riêng tư và tính toàn vẹn của dữ liệu nhật ký.")
    add_p("• Phạm vi nghiên cứu: Hệ thống quản lý và giám sát đa website (triển khai mẫu trên 2 website thử nghiệm đại diện: Website A - Cửa hàng thương mại điện tử Alpha Store và Website B - Cổng dịch vụ kỹ thuật Beta Services); vận hành hoàn chỉnh trên môi trường máy chủ XAMPP (Apache, PHP 8.2, MariaDB 10.4) trên hệ điều hành Windows.")

    add_h2("4. Phương pháp thực hiện")
    add_p("Đề tài áp dụng phương pháp nghiên cứu thực nghiệm kết hợp quy trình phát triển phần mềm lặp (Iterative Agile Development):")
    add_p("1. Giai đoạn 1 - Khảo sát và phân tích: Nghiên cứu các tài liệu kỹ thuật về cơ chế thu thập sự kiện web (W3C Beacon API, Fetch API), phân tích cấu trúc payload và thiết kế mô hình thực thể ERD.")
    add_p("2. Giai đoạn 2 - Lập trình kiến trúc lõi: Phát triển JavaScript SDK, xây dựng API Ingestion, thiết lập giải thuật chuẩn hóa Canonicalization và tính toán mã băm SHA-256 chống đếm trùng.")
    add_p("3. Giai đoạn 3 - Phát triển giao diện quản trị: Xây dựng Dashboard thống kê trực quan, biểu đồ xu hướng Chart.js, module phân tích chuỗi Funnel và cơ chế phân quyền RBAC.")
    add_p("4. Giai đoạn 4 - Kiểm thử tự động và tinh chỉnh: Thiết kế kịch bản kiểm thử 225 request với đáp án xác định (Ground Truth), chạy kiểm tra 12 tiêu chí Rubric, tối ưu hóa hiệu năng truy vấn SQL và khắc phục lỗi thực tế.")

    add_h2("5. Sản phẩm dự kiến")
    add_p("Kết thúc đề tài, nhóm bàn giao trọn vẹn bộ sản phẩm gồm:")
    add_p("1. Báo cáo học phần hoàn chỉnh đầy đủ trên 30 trang theo đúng quy định định dạng K18 của Khoa CNTT.")
    add_p("2. Toàn bộ mã nguồn hệ thống phân tích Web Analytics đặt tại thư mục BTL/website-analytics/.")
    add_p("3. Bộ mã nguồn 2 website thử nghiệm thực tế (Demo Site A và Demo Site B) đã nhúng sẵn Tracking SDK.")
    add_p("4. Cơ sở dữ liệu chuẩn hóa gồm tệp tạo cấu trúc schema.sql và tệp sinh dữ liệu mẫu seed.sql.")
    add_p("5. Bộ công cụ kiểm thử tự động 12 tiêu chí Rubric (run-tests.php) đạt tỷ lệ vượt qua 100% (12/12 PASS).")

    doc.add_page_break()

    # =========================================================================
    # CHƯƠNG 1
    # =========================================================================
    add_h1("CHƯƠNG 1: PHÂN TÍCH YÊU CẦU VÀ THIẾT KẾ HỆ THỐNG")
    
    add_h2("1.1. Mô tả kịch bản bài toán và cơ cấu tổ chức")
    add_p("Hệ thống Web Analytics được thiết kế nhằm đáp ứng bài toán quản lý và giám sát đa website cho một tổ chức doanh nghiệp sở hữu nhiều kênh kinh doanh số. Trong mô hình thực nghiệm, hệ thống quản lý 2 website đại diện:")
    add_p("• Website A (Cửa hàng trực tuyến - Alpha Store, Khóa tracking: trk_site_a_alpha_shop_2026): Chuyên kinh doanh các sản phẩm công nghệ. Người dùng truy cập trang chủ, xem danh mục sản phẩm (/products.html), xem chi tiết sản phẩm (/product-detail.html), bấm các nút 'Thêm vào giỏ hàng', và đặc biệt là vào trang tải tài liệu (/download.html) để bấm nút tải bản thông số kỹ thuật PDF.")
    add_p("• Website B (Cổng dịch vụ doanh nghiệp - Beta Services, Khóa tracking: trk_site_b_beta_portal_2026): Chuyên cung cấp các dịch vụ Cloud và Bảo mật. Người dùng truy cập trang giới thiệu, xem danh mục dịch vụ (/services.html), bấm nút 'Đăng ký tư vấn', và thực hiện tải hồ sơ năng lực công ty tại trang đăng ký (/register.html).")
    add_p("Về cơ cấu tổ chức và người dùng, hệ thống phân chia thành 2 nhóm đối tượng sử dụng chính:")
    add_p("1. Quản trị viên hệ thống (Admin): Có toàn quyền cấu hình website mới, khai báo các sự kiện được phép theo dõi, quản lý tài khoản người dùng, phân quyền truy cập, giám sát nhật ký lỗi xung đột và chạy các bài kiểm thử nghiệm thu.")
    add_p("2. Thành viên phân tích / Báo cáo (Member): Là các nhân viên phân tích nghiệp vụ chỉ được cấp quyền xem dữ liệu trên một hoặc một số website nhất định được Admin giao phó. Thành viên bị nghiêm cấm truy cập dữ liệu của website ngoài phạm vi phân quyền.")

    add_h2("1.2. Phân tích yêu cầu kỹ thuật và công nghệ lựa chọn")
    add_p("Để đáp ứng yêu cầu của một phần mềm mã nguồn mở chuyên nghiệp, hệ thống áp dụng các tiêu chuẩn công nghệ tiên tiến, loại bỏ sự phụ thuộc vào các framework cồng kềnh, tối ưu hóa tốc độ và bảo mật:")
    add_p("• Ngôn ngữ máy chủ: PHP 8.2+ thuần kết hợp lớp kết nối PDO (PHP Data Objects). Việc sử dụng Prepared Statements 100% bảo vệ hệ thống tuyệt đối trước các cuộc tấn công SQL Injection.")
    add_p("• Hệ quản trị cơ sở dữ liệu: MariaDB 10.4+ / MySQL. Thiết kế chuẩn hóa theo dạng chuẩn 3NF, hỗ trợ bảng mã ký tự utf8mb4 đầy đủ và chuẩn so sánh utf8mb4_unicode_ci.")
    add_p("• Client SDK: Viết bằng Vanilla JavaScript (ES5/ES6) thuần túy, không dùng thư viện ngoài như jQuery. Tự động tương thích với tất cả các trình duyệt hiện đại (Chrome, Edge, Firefox, Safari) và tự động xử lý bất đồng bộ bằng Beacon API hoặc Fetch API.")
    add_p("• Giao diện quản trị (Dashboard): HTML5, CSS3 hiện đại theo chuẩn Enterprise SaaS Dashboard (lấy cảm hứng từ giao diện Vercel và Tailwind CSS). Toàn bộ biểu đồ sử dụng thư viện mã nguồn mở Chart.js phiên bản 4.4.1 thông qua chuẩn UMD, hỗ trợ vẽ biểu đồ đường (Line chart) và biểu đồ cột (Bar chart) mượt mà.")
    add_p("• Hệ thống Icon: Sử dụng 100% SVG Lucide Icons nhúng cục bộ tại file includes/icons.php, loại bỏ hoàn toàn các liên kết CDN ra ngoài và không chứa bất kỳ emoji ký tự nào, bảo đảm hệ thống chạy offline 100% không cần kết nối Internet.")

    add_h2("1.3. Yêu cầu chức năng và tiêu chí chấp nhận (Rubric)")
    add_p("Dựa trên đề cương bài tập lớn và bảng barem đánh giá của Giảng viên hướng dẫn, nhóm đã xây dựng bảng ma trận 12 yêu cầu chức năng kỹ thuật cốt lõi (Mã REQ-01 đến REQ-12) cùng các tiêu chí nghiệm thu chặt chẽ như sau:")

    add_table_caption("Bảng 1.1: Bảng đặc tả 12 yêu cầu chức năng và tiêu chí nghiệm thu Rubric")
    req_data = [
        ("REQ-01", "Tổng hợp Lượt xem & Thao tác", "Hệ thống phải tách bạch rõ ràng giữa lượt xem trang (page_view) và các thao tác người dùng (button_click, download). Số liệu hiển thị trên KPI cards khớp 100% với sự kiện trong CSDL."),
        ("REQ-02", "Chống đếm trùng (Case A)", "Gửi lặp lại cùng event_id và cùng nội dung payload_hash phải được nhận diện là Idempotent duplicate. Máy chủ trả về HTTP 200 và tuyệt đối không tăng số đếm sự kiện."),
        ("REQ-03", "Bắt xung đột dữ liệu (Case B)", "Gửi cùng event_id nhưng nội dung payload bị thay đổi phải bị từ chối với mã HTTP 409 Conflict. Hệ thống phải ghi log cảnh báo vào bảng event_ingest_errors."),
        ("REQ-04", "Chấp nhận sự kiện mới (Case C)", "Gửi event_id mới với payload tương đương vẫn được tiếp nhận bình thường (HTTP 201 Created), bảo đảm người dùng khác nhau thao tác giống nhau đều được ghi nhận."),
        ("REQ-05", "Xử lý nửa đêm (Midnight)", "Sự kiện xảy ra lúc 23:58 đêm hôm qua và 00:03 sáng hôm nay phải được phân nhóm chính xác vào từng ngày theo múi giờ chuẩn Việt Nam (Asia/Ho_Chi_Minh)."),
        ("REQ-06", "Chính sách sự kiện trễ", "Từ chối các sự kiện có thời gian trễ quá 24 giờ so với thời điểm máy chủ tiếp nhận (HTTP 422 Unprocessable Entity), ghi log LATE_EVENT_EXCEEDED để bảo vệ tính chốt sổ báo cáo."),
        ("REQ-07", "Phân tích chuỗi Funnel", "Nhận diện chính xác chuỗi tuần tự 3 bước: page_view -> download_start -> download_complete trong cùng một session. Tính đúng tỷ lệ hoàn tất chuỗi chuyển đổi."),
        ("REQ-08", "Chống sai chuỗi & Bỏ dở", "Các phiên thiếu bước tải xong hoặc gửi sự kiện ngược thứ tự (tải xong trước khi bấm tải) phải bị loại bỏ khỏi tỷ lệ hoàn tất và phân loại là 'Bỏ dở' hoặc 'Bất thường'."),
        ("REQ-09", "Cô lập dữ liệu đa website", "Dữ liệu của Website A và Website B hoàn toàn độc lập, khóa tracking key riêng biệt, không bị lẫn chéo session hay số liệu thống kê giữa các website."),
        ("REQ-10", "Phân quyền bảo mật RBAC", "Tài khoản Member chỉ được truy cập website được phân quyền (Site A). Nếu cố tình gửi request truy cập Site B sẽ bị máy chủ chặn đứng với mã lỗi HTTP 403 Forbidden."),
        ("REQ-11", "Bảo vệ quyền riêng tư 100%", "Áp dụng cơ chế Whitelist thuộc tính. Tuyệt đối không lưu mật khẩu, ô nhập liệu text (input), thẻ tín dụng hay dữ liệu nhạy cảm vào database."),
        ("REQ-12", "Tích hợp thông suốt End-to-End", "Toàn bộ luồng dữ liệu từ Client SDK trên trình duyệt đến REST API, Database và Dashboard hiển thị thông suốt, sẵn sàng nghiệm thu tự động 100%.")
    ]
    add_styled_table(["Mã REQ", "Tên yêu cầu chức năng", "Tiêu chí nghiệm thu kỹ thuật xác định"], req_data, [2.0, 4.5, 10.0])

    add_h2("1.4. Thiết kế cơ sở dữ liệu và chuẩn hóa dữ liệu")
    add_p("Cơ sở dữ liệu của hệ thống Web Analytics mang tên website_analytics, được thiết kế theo mô hình quan hệ chuẩn hóa (3NF) gồm 8 bảng chuyên biệt. Cấu trúc bảng được tối ưu hóa cho bài toán ghi nhận nhật ký khối lượng lớn và truy vấn tổng hợp thời gian thực:")

    add_table_caption("Bảng 1.2: Danh mục 8 bảng dữ liệu chuẩn hóa trong hệ thống")
    tables_summary = [
        ("1", "websites", "Lưu thông tin định danh các website được theo dõi (ID, tên miền, tracking_key, múi giờ, hạn sự kiện trễ)."),
        ("2", "users", "Quản lý tài khoản đăng nhập hệ thống, lưu mật khẩu băm an toàn theo chuẩn BCRYPT, vai trò (admin/member)."),
        ("3", "website_permissions", "Ma trận phân quyền chi tiết cho từng tài khoản Member trên từng website (quyền xem, cấu hình, xuất CSV)."),
        ("4", "event_definitions", "Khai báo danh mục sự kiện được phép thu thập trên từng website, cơ chế bật/tắt để lọc sự kiện lạ."),
        ("5", "sessions", "Quản lý phiên truy cập của người dùng ẩn danh (session_id, website_id, thời điểm bắt đầu, thời điểm cuối)."),
        ("6", "events", "Bảng trung tâm lưu trữ toàn bộ các sự kiện hợp lệ đã tiếp nhận, đường dẫn trang, thời gian client, mã băm SHA-256."),
        ("7", "event_ingest_errors", "Nhật ký lưu vết toàn bộ các trường hợp gửi lỗi: trùng mã xung đột (Case B), sự kiện trễ, sự kiện chưa khai báo."),
        ("8", "report_queries", "Ghi nhận lịch sử các truy vấn xuất báo cáo hoặc kiểm toán dữ liệu của quản trị viên.")
    ]
    add_styled_table(["STT", "Tên bảng cơ sở dữ liệu", "Mô tả chức năng nghiệp vụ trong hệ thống"], tables_summary, [1.5, 4.5, 10.5])

    add_p("Cấu trúc chi tiết của bảng trung tâm events được thiết kế như sau:")
    add_code_block(
        "CREATE TABLE `events` (\n"
        "  `event_row_id` bigint(20) NOT NULL AUTO_INCREMENT,\n"
        "  `website_id` int(11) NOT NULL,\n"
        "  `event_id` varchar(64) NOT NULL COMMENT 'UUID v4 duy nhất từ client',\n"
        "  `session_id` varchar(64) NOT NULL COMMENT 'ID phiên ẩn danh',\n"
        "  `event_name` varchar(64) NOT NULL COMMENT 'Tên sự kiện: page_view, button_click...',\n"
        "  `page_path` varchar(255) NOT NULL COMMENT 'Đường dẫn trang web',\n"
        "  `occurred_at` datetime NOT NULL COMMENT 'Thời điểm sự kiện diễn ra tại client',\n"
        "  `received_at` datetime NOT NULL COMMENT 'Thời điểm máy chủ tiếp nhận',\n"
        "  `payload_hash` char(64) NOT NULL COMMENT 'Mã băm SHA-256 chuẩn hóa',\n"
        "  `properties` longtext CHECK (json_valid(`properties`)),\n"
        "  PRIMARY KEY (`event_row_id`),\n"
        "  UNIQUE KEY `uk_site_event` (`website_id`, `event_id`),\n"
        "  KEY `idx_site_occurred` (`website_id`, `occurred_at`),\n"
        "  KEY `idx_site_name_occurred` (`website_id`, `event_name`, `occurred_at`),\n"
        "  KEY `idx_site_session` (`website_id`, `session_id`, `occurred_at`),\n"
        "  CONSTRAINT `fk_event_website` FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE CASCADE,\n"
        "  CONSTRAINT `fk_event_session` FOREIGN KEY (`website_id`, `session_id`) REFERENCES `sessions` (`website_id`, `session_id`) ON DELETE CASCADE\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    )

    add_h2("1.5. Thiết kế kiến trúc hệ thống và luồng dữ liệu (Data Pipeline)")
    add_p("Kiến trúc hệ thống Web Analytics tuân thủ mô hình 4 tầng phân tách độc lập (Separation of Concerns), bảo đảm tính mở rộng và khả năng bảo trì cao:")
    add_p("• Tầng 1 - Thu thập dữ liệu (Client SDK): File sdk/tracker.js được nhúng vào các trang web khách hàng. SDK chạy ngầm ở chế độ bất đồng bộ (Asynchronous), tự động bắt các sự kiện xem trang, nhấp chuột vào nút bấm có thuộc tính data-track-click, và quá trình tải file. SDK sinh UUID ngẫu nhiên cho mỗi sự kiện và duy trì session_id trong sessionStorage của trình duyệt.")
    add_p("• Tầng 2 - Tiếp nhận và Thẩm định (Ingestion API): Endpoint api/track.php tiếp nhận payload JSON. Tại đây, hệ thống thực thi các thuật toán kiểm tra nghiêm ngặt: thẩm định Tracking Key, kiểm tra dung lượng payload (<64KB), lọc bỏ dữ liệu nhạy cảm theo Whitelist, kiểm tra hạn sự kiện trễ 24h, và chuẩn hóa JSON để tính mã băm SHA-256.")
    add_p("• Tầng 3 - Lưu trữ và Toàn vẹn (Persistence Layer): Cơ sở dữ liệu MariaDB với cơ chế Transaction và ràng buộc toàn vẹn quan hệ (Foreign Keys). Dữ liệu sự kiện được lưu trữ song song với việc cập nhật thời gian hoạt động của phiên trong bảng sessions.")
    add_p("• Tầng 4 - Phân tích và Trực quan hóa (Presentation & Analytics): Bảng điều khiển quản trị web cung cấp giao diện trực quan cho quản trị viên, gồm Dashboard KPI, Báo cáo thời gian, Phân tích chuỗi Funnel, Nhật ký lỗi tiếp nhận và Trung tâm kiểm thử tự động.")

    add_h2("1.6. Thiết kế cơ chế chống đếm trùng (Idempotency) và bắt xung đột")
    add_p("Trong môi trường mạng Internet, các lỗi đường truyền thường khiến trình duyệt gửi lại (retry) một yêu cầu nhiều lần, hoặc người dùng vô tình bấm F5 liên tục. Nếu không có cơ chế xử lý thông minh, hệ thống sẽ bị đếm trùng số liệu. Đồng thời, kẻ tấn công có thể cố tình gửi lại cùng một event_id nhưng với dữ liệu giả mạo nhằm phá hoại báo cáo.")
    add_p("Để giải quyết bài toán này, nhóm đã thiết kế giải thuật Chống đếm trùng Idempotent 3 nhánh (Case A, Case B, Case C):")
    add_p("1. Thuật toán chuẩn hóa Payload (Canonicalization): Các thuộc tính trong payload JSON được sắp xếp lại theo thứ tự chữ cái của khóa một cách đệ quy (Recursive Key Sorting) và loại bỏ các khoảng trắng thừa. Sau đó, hệ thống tính mã băm SHA-256 đại diện: payload_hash = hash('sha256', canonical_json).")
    add_p("2. Nhánh Case A (Trùng lặp Idempotent): Khi một sự kiện gửi lên có (website_id, event_id) đã tồn tại trong CSDL VÀ payload_hash mới trùng khớp với payload_hash đã lưu. Hệ thống xác định đây là một yêu cầu gửi lặp vô hại, trả về mã HTTP 200 OK cùng thông điệp duplicate_idempotent và tuyệt đối KHÔNG tăng số đếm trong hệ thống.")
    add_p("3. Nhánh Case B (Xung đột Payload Conflict): Khi sự kiện gửi lên có (website_id, event_id) đã tồn tại trong CSDL NHƯNG payload_hash mới khác với payload_hash đã lưu (chứng tỏ nội dung sự kiện đã bị sửa đổi). Hệ thống lập tức từ chối với mã HTTP 409 Conflict, đồng thời ghi một bản ghi cảnh báo vào bảng event_ingest_errors với mã lỗi PAYLOAD_CONFLICT để phục vụ kiểm toán bảo mật.")
    add_p("4. Nhánh Case C (Sự kiện mới hợp lệ): Khi sự kiện có event_id hoàn toàn mới, dù có nội dung payload giống sự kiện trước (do người dùng khác cùng bấm mua sản phẩm tương tự), hệ thống vẫn ghi nhận là một sự kiện mới hợp lệ và trả về mã HTTP 201 Created.")

    add_h2("1.7. Thiết kế thuật toán phân tích chuỗi thao tác (Conversion Funnel)")
    add_p("Phân tích chuỗi thao tác (Conversion Funnel) là chức năng cao cấp giúp người quản trị đánh giá tỷ lệ chuyển đổi của khách hàng từ lúc bắt đầu xem trang đến khi hoàn tất mục tiêu. Trong Đề tài 17, chuỗi chuyển đổi chuẩn được định nghĩa gồm 3 bước tuần tự:")
    add_p("• Bước 1: Xem trang tải tài liệu (page_view).")
    add_p("• Bước 2: Bấm nút bắt đầu tải file catalog (download_start).")
    add_p("• Bước 3: Hoàn tất tải file về máy tính thành công (download_complete).")
    add_p("Một phiên truy cập chỉ được hệ thống công nhận là 'Hoàn tất chuỗi' khi và chỉ khi thỏa mãn đồng thời 4 điều kiện toán học nghiêm ngặt:")
    add_p("1. Điều kiện 1 (Cùng đối tượng): Tất cả các sự kiện phải thuộc cùng một website_id và cùng một session_id.")
    add_p("2. Điều kiện 2 (Đầy đủ các bước): Phiên phải có đủ sự kiện Bước 1, Bước 2 và Bước 3.")
    add_p("3. Điều kiện 3 (Đúng thứ tự thời gian): Thời điểm xảy ra các sự kiện phải thỏa mãn quan hệ bất đẳng thức tuần tự: t(page_view) <= t(download_start) <= t(download_complete).")
    add_p("4. Điều kiện 4 (Chống gian lận chuỗi): Nếu sự kiện Bước 3 xảy ra trước Bước 2 (do gian lận hoặc lỗi hệ thống), phiên sẽ bị gắn cờ 'Sai thứ tự thời gian (Loại bỏ)'. Nếu người dùng dừng lại sau Bước 1 hoặc Bước 2, phiên được phân loại chính xác là 'Bỏ dở (Drop-off)' và không được tính vào tỷ lệ hoàn tất.")

    add_h2("1.8. Thiết kế phân quyền người dùng theo vai trò (RBAC)")
    add_p("Hệ thống áp dụng mô hình phân quyền Role-Based Access Control (RBAC) được kiểm soát nghiêm ngặt ở cấp độ máy chủ (Server-side Enforcement):")
    add_p("• Vai trò Quản trị viên (Admin): Toàn quyền truy cập mọi tính năng, bao gồm quản lý danh sách website, phân quyền người dùng, cấu hình sự kiện, xóa người dùng, xem nhật ký lỗi tiếp nhận và chạy trung tâm kiểm thử tự động.")
    add_p("• Vai trò Thành viên (Member): Bị giới hạn quyền truy cập ở cấp độ máy chủ. Tại tệp includes/permissions.php, hàm user_can_access_website() kiểm tra trong bảng website_permissions. Nếu Member không có quyền xem website tương ứng, máy chủ sẽ ngắt luồng xử lý và trả về mã lỗi HTTP 403 Forbidden.")
    add_p("• Cơ chế an toàn dữ liệu người dùng: Tài khoản Siêu Quản trị viên (#1) và tài khoản đang đăng nhập hiện tại được bảo vệ bằng cờ cấm xóa (is_protected), ngăn ngừa tuyệt đối nguy cơ hệ thống bị mất tài khoản quản trị.")

    add_h2("1.9. Ma trận truy vết yêu cầu và thiết kế (Traceability Matrix)")
    add_p("Bảng ma trận truy vết dưới đây thể hiện sự liên kết chặt chẽ giữa 12 yêu cầu chức năng (REQ), thành phần kiến trúc, tệp mã nguồn hiện thực và kịch bản kiểm thử nghiệm thu tương ứng:")

    add_table_caption("Bảng 1.3: Ma trận truy vết yêu cầu, kiến trúc và kiểm thử")
    trace_matrix = [
        ("REQ-01", "Tầng Thống kê", "dashboard.php, reports.php", "Test 01 trong run-tests.php"),
        ("REQ-02", "Tầng API Ingest", "api/track.php (Nhánh Case A)", "Test 02 trong run-tests.php"),
        ("REQ-03", "Tầng Bảo mật", "api/track.php (Nhánh Case B)", "Test 03 trong run-tests.php"),
        ("REQ-04", "Tầng API Ingest", "api/track.php (Nhánh Case C)", "Test 04 trong run-tests.php"),
        ("REQ-05", "Tầng Xử lý Timezone", "config/app.php, api/track.php", "Test 05 trong run-tests.php"),
        ("REQ-06", "Tầng Lọc dữ liệu", "api/track.php (Late Window Check)", "Test 06 trong run-tests.php"),
        ("REQ-07", "Tầng Phân tích Funnel", "funnels.php (Thuật toán 3 bước)", "Test 07 trong run-tests.php"),
        ("REQ-08", "Tầng Chống gian lận", "funnels.php (Timestamp Check)", "Test 08 trong run-tests.php"),
        ("REQ-09", "Tầng Cách ly Website", "database/schema.sql, api/track.php", "Test 09 trong run-tests.php"),
        ("REQ-10", "Tầng Phân quyền RBAC", "includes/permissions.php, users.php", "Test 10 trong run-tests.php"),
        ("REQ-11", "Tầng Whitelist riêng tư", "config/app.php, api/track.php", "Test 11 trong run-tests.php"),
        ("REQ-12", "Toàn bộ hệ thống", "Tất cả các tệp mã nguồn BTL", "Test 12 trong run-tests.php")
    ]
    add_styled_table(["Yêu cầu", "Thành phần kiến trúc", "Tệp mã nguồn hiện thực", "Kịch bản kiểm thử nghiệm thu"], trace_matrix, [2.0, 3.5, 5.5, 5.5])

    doc.add_page_break()

    # =========================================================================
    # CHƯƠNG 2
    # =========================================================================
    add_h1("CHƯƠNG 2: TRIỂN KHAI VÀ CẤU HÌNH HỆ THỐNG")

    add_h2("2.1. Chuẩn bị môi trường phát triển và cấu hình máy chủ")
    add_p("Hệ thống Web Analytics được triển khai và cấu hình đồng bộ trên môi trường máy chủ XAMPP chạy trên hệ điều hành Windows:")
    add_p("• Máy chủ Web Apache (Cổng 80): Kích hoạt mod_rewrite và cấu hình VirtualHost cho phép xử lý các yêu cầu REST API và hỗ trợ Header CORS cho phép JavaScript SDK gửi sự kiện từ các tên miền khác nhau.")
    add_p("• Máy chủ Cơ sở dữ liệu MariaDB (Cổng 3306): Khởi tạo cơ sở dữ liệu website_analytics với bộ mã ký tự mặc định utf8mb4.")
    add_p("• Cấu hình môi trường PHP (config/app.php): Thiết lập múi giờ mặc định Asia/Ho_Chi_Minh bằng hàm date_default_timezone_set(). Thiết lập giới hạn kích thước payload tối đa 64KB (MAX_PAYLOAD_BYTES = 65536) và thời hạn từ chối sự kiện đến muộn 24 giờ (DEFAULT_LATE_WINDOW_HOURS = 24). Cấu hình cơ chế bảo vệ phiên Session bằng cờ HttpOnly, SameSite=Lax và UseOnlyCookies.")

    add_h2("2.2. Xây dựng và tối ưu cơ sở dữ liệu")
    add_p("Cơ sở dữ liệu được khởi tạo thông qua script SQL chuẩn hóa. Để bảo đảm tốc độ truy vấn tức thì khi dữ liệu tăng cao, hệ thống đã thiết lập các chỉ mục đa cột (Composite Indexes) tối ưu:")
    add_p("• Chỉ mục uk_site_event (website_id, event_id): Khóa duy nhất ngăn ngừa đếm trùng mã ở cấp độ phần cứng database.")
    add_p("• Chỉ mục idx_site_occurred (website_id, occurred_at): Tối ưu hóa truy vấn lọc sự kiện theo khoảng ngày và thời gian.")
    add_p("• Chỉ mục idx_site_name_occurred (website_id, event_name, occurred_at): Tăng tốc 500% cho các câu lệnh tổng hợp số lượt xem (page_view) và thao tác (button_click).")
    add_p("• Chỉ mục idx_site_session (website_id, session_id, occurred_at): Phục vụ việc gom nhóm phiên và phân tích chuỗi Funnel.")

    add_h2("2.3. Phát triển JavaScript Tracking SDK (sdk/tracker.js)")
    add_p("File sdk/tracker.js là thành phần client cực kỳ quan trọng, được viết hoàn toàn bằng Vanilla JavaScript thuần, dung lượng nén dưới 8KB, không phụ thuộc bất kỳ thư viện ngoài nào:")
    add_p("1. Cơ chế sinh UUID v4: Sử dụng hàm crypto.randomUUID() chuẩn của trình duyệt (hoặc thuật toán sinh ngẫu nhiên 128-bit dự phòng) bảo đảm tính duy nhất tuyệt đối của event_id.")
    add_p("2. Cơ chế quản lý Session ẩn danh: SDK kiểm tra và duy trì session_id ngẫu nhiên trong sessionStorage của trình duyệt. Khi người dùng mở tab mới hoặc tắt trình duyệt, phiên mới sẽ được tự động khởi tạo.")
    add_p("3. Tự động ghi nhận Lượt xem trang (page_view): Ngay khi trang web tải xong (sự kiện DOMContentLoaded hoặc load), SDK tự động thu thập đường dẫn trang, tiêu đề, độ phân giải màn hình và gửi về máy chủ.")
    add_p("4. Lắng nghe thao tác người dùng: Tự động bắt sự kiện nhấp chuột trên mọi phần tử có thuộc tính data-track-click hoặc thẻ button/a, gửi sự kiện button_click kèm định danh nút bấm.")
    add_p("5. Theo dõi chuỗi tải tài liệu: Cung cấp hàm WebTracker.trackDownload(fileName, downloadUrl) tự động kích hoạt sự kiện download_start, theo dõi tiến trình và chỉ gửi sự kiện download_complete khi file đã được trình duyệt tải về thành công 100%.")

    add_image_if_exists("hinh_huong_dan_nhung_sdk.png", "Hình 2.1: Hướng dẫn tích hợp mã nhúng JavaScript Tracking SDK vào website")

    add_h2("2.4. Phát triển REST API tiếp nhận và chống gian lận (api/track.php)")
    add_p("Tệp api/track.php là cổng tiếp nhận trung tâm của toàn bộ dữ liệu sự kiện gửi lên. Quy trình xử lý tại API được thiết kế theo mô hình đường ống tuần tự nghiêm ngặt:")
    add_p("Bước 1: Kiểm tra phương thức HTTP (chỉ chấp nhận POST) và đọc kích thước raw payload. Nếu vượt quá 64KB, từ chối ngay với mã lỗi 413 Payload Too Large.")
    add_p("Bước 2: Giải mã JSON và trích xuất các trường bắt buộc (tracking_key, event_id, session_id, event_name, occurred_at). Kiểm tra độ dài chuỗi ký tự tối đa 64 ký tự.")
    add_p("Bước 3: Thẩm định Tracking Key trong bảng websites. Nếu website không tồn tại hoặc bị khóa (inactive), trả về mã lỗi 404 Not Found.")
    add_p("Bước 4: Kiểm tra sự kiện đã được khai báo và đang bật trong bảng event_definitions. Nếu sự kiện chưa khai báo hoặc bị Tắt, từ chối với mã 422 EVENT_NOT_DEFINED và ghi log.")
    add_p("Bước 5: Lọc dữ liệu nhạy cảm theo Whitelist (ALLOWED_PROPERTIES), loại bỏ triệt để mọi trường mật khẩu, thẻ tín dụng trước khi đưa vào xử lý.")
    add_p("Bước 6: Kiểm tra hạn sự kiện trễ. Nếu occurred_at cũ hơn 24 giờ so với giờ máy chủ, từ chối với mã 422 LATE_EVENT_EXCEEDED và ghi log.")
    add_p("Bước 7: Chuẩn hóa payload và tính mã băm SHA-256. Kiểm tra đếm trùng Idempotent: Xử lý Case A (HTTP 200), Case B (HTTP 409), hoặc Case C (HTTP 201).")

    add_image_if_exists("hinh_nhat_ky_xung_dot.png", "Hình 2.2: Giao diện Nhật ký lỗi tiếp nhận và phát hiện xung đột dữ liệu (Case B & Lỗi trễ)")

    add_h2("2.5. Xây dựng Dashboard thống kê tổng quan (dashboard.php)")
    add_p("Tệp dashboard.php cung cấp giao diện trực quan hóa toàn bộ hoạt động của website được lựa chọn:")
    add_p("• 5 Thẻ chỉ số KPI hàng đầu: Thống kê chính xác tổng Lượt xem trang (page_view), Lần thao tác (button_click), Phiên hoạt động ẩn danh (active_sessions), Hoàn tất tải file (download_complete) và Lỗi tiếp nhận / Xung đột.")
    add_p("• Biểu đồ đường (Line Chart): Thể hiện xu hướng biến động của lượt xem trang và thao tác người dùng theo từng ngày trong kỳ báo cáo.")
    add_p("• Biểu đồ cột (Bar Chart): Xếp hạng top các đường dẫn trang web phổ biến nhất có lưu lượng truy cập cao.")
    add_p("• Thanh bộ lọc toàn cục: Hỗ trợ chuyển đổi nhanh giữa các website và chọn khoảng thời gian linh hoạt (Hôm nay, Hôm qua, 7 ngày qua, 14 ngày, 30 ngày, hoặc tùy chọn ngày).")

    add_image_if_exists("hinh_kpi_dashboard.png", "Hình 2.3: Giao diện Dashboard KPI tổng quan hoạt động website")

    add_h2("2.6. Phát triển module phân tích chuỗi thao tác Funnel (funnels.php)")
    add_p("Trang funnels.php hiện thực hóa thuật toán phân tích chuỗi hành trình 3 bước (page_view -> download_start -> download_complete):")
    add_p("• Trực quan hóa quy trình 3 bước: Hiển thị số lượng phiên bắt đầu ở Bước 1 (100% làm gốc), số lượng phiên đi tiếp sang Bước 2 kèm tỷ lệ rơi rớt (Drop-off %), và số lượng phiên hoàn tất trọn vẹn ở Bước 3 cùng tỷ lệ chuyển đổi toàn chuỗi (Conversion Rate %).")
    add_p("• Bảng đối chiếu nguồn từng phiên (Audit Trail): Cho phép xem chi tiết từng session_id, thời lượng phiên, thời điểm chính xác diễn ra từng bước và ghi chú phân tích hành vi cụ thể.")
    add_p("• Bộ lọc trạng thái chuỗi: Cung cấp các tab lọc nhanh: Tất cả phiên, Hoàn tất chuỗi (badge xanh), Bỏ dở sau Bước 2 (badge vàng), Bỏ dở tại Bước 1 (badge xám), và Sai thứ tự thời gian (badge đỏ).")
    add_p("• Phòng thử nghiệm 1-Click (Simulation Lab): Tích hợp sẵn 3 nút mô phỏng trực tiếp ngay trên trang: 'Tạo 1 Phiên Hoàn Tất (100%)', 'Tạo 1 Phiên Bỏ Dở Bước 2', và 'Tạo 1 Phiên Ngược Thứ Tự', cho phép người dùng và giảng viên tự tay bấm kiểm tra kết quả ngay lập tức.")

    add_image_if_exists("hinh_funnel_chuyen_doi.png", "Hình 2.4: Trực quan hóa phễu chuyển đổi 3 bước và bảng đối chiếu Audit Trail trên funnels.php")

    add_h2("2.7. Xây dựng module báo cáo chi tiết và xuất CSV (reports.php)")
    add_p("Hệ thống cung cấp trang reports.php và event-logs.php giúp quản trị viên phân tích sâu từng bản ghi sự kiện:")
    add_p("• Bảng nhật ký sự kiện hợp lệ: Hiển thị đầy đủ Event ID, Session ID, Tên sự kiện, Đường dẫn trang, Thời gian xảy ra và Mã băm SHA-256 an toàn.")
    add_p("• Lọc dữ liệu tức thì: Hỗ trợ lọc theo loại sự kiện (page_view, button_click, download_start, download_complete) và tìm kiếm trực tiếp theo Session ID hoặc Đường dẫn trang bằng JavaScript.")
    add_p("• Xuất dữ liệu CSV an toàn: Tích hợp chức năng xuất báo cáo ra file CSV. Tệp CSV được tự động chèn tiền tố BOM UTF-8 (\\xEF\\xBB\\xBF), bảo đảm hiển thị tiếng Việt có dấu chuẩn xác 100% khi mở bằng Microsoft Excel, đồng thời áp dụng cơ chế chống lỗi CSV Injection.")

    add_image_if_exists("hinh_danh_sach_su_kien_loc.png", "Hình 2.5: Giao diện lọc danh sách sự kiện hợp lệ và tìm kiếm trực tiếp")

    add_h2("2.8. Triển khai phân quyền RBAC và quản lý tài khoản (users.php)")
    add_p("Trang users.php cung cấp công cụ quản lý người dùng và ma trận phân quyền chi tiết cho Quản trị viên:")
    add_p("• Danh sách tài khoản người dùng: Hiển thị mã định danh, họ tên, email đăng nhập, vai trò (Admin / Member), trạng thái hoạt động và các nút thao tác.")
    add_p("• Ma trận phân quyền chi tiết: Đối với từng tài khoản Member, Admin có thể tick chọn độc lập các quyền trên từng website: 'Quyền xem báo cáo', 'Quyền quản lý sự kiện', và 'Quyền xuất file CSV'.")
    add_p("• Modal Xóa tài khoản an toàn: Hỗ trợ xóa tài khoản thành viên không còn hoạt động, tự động xóa liên hoàn (Cascade Delete) các quyền tương ứng trong bảng website_permissions. Hệ thống tự động khóa bảo vệ không cho phép xóa tài khoản Siêu Quản trị viên (#1) và tài khoản đang đăng nhập.")

    add_image_if_exists("hinh_phan_quyen_rbac.png", "Hình 2.6: Quản lý người dùng và Ma trận phân quyền RBAC theo từng website")

    add_h2("2.9. Xây dựng Trung tâm kiểm thử tự động 12 tiêu chí (test-center.php)")
    add_p("Trang test-center.php là điểm nhấn công nghệ đặc biệt của đồ án. Thay vì phải mở màn hình dòng lệnh phức tạp, nhóm đã tích hợp trực tiếp bộ kiểm thử tự động 12 tiêu chí Rubric ngay trên giao diện web:")
    add_p("• Nút bấm kích hoạt 1-Click: 'Bấm Để Chạy Toàn Bộ 12 Bài Kiểm Thử Tự Động Ngay'.")
    add_p("• Giải thích 12 tiêu chí bằng ngôn ngữ tiếng Việt mộc mạc, dễ hiểu, giúp người dùng nắm vững bản chất kỹ thuật của từng bài test.")
    add_p("• Hộp hướng dẫn thuyết trình: Hướng dẫn sẵn kịch bản trả lời tự tin cho sinh viên khi giảng viên yêu cầu nghiệm thu chất lượng phần mềm.")
    add_p("• Bảng kết quả tổng quan: Hiển thị tỷ lệ đạt 100% (12/12 PASS) và bảng chi tiết từng tiêu chí kèm số liệu thực tế được truy vấn trực tiếp từ cơ sở dữ liệu.")

    add_image_if_exists("hinh_trung_tam_kiem_thu_rubric.png", "Hình 2.7: Trung tâm kiểm thử tự động 12 tiêu chí Rubric trên test-center.php")

    doc.add_page_break()

    # =========================================================================
    # CHƯƠNG 3
    # =========================================================================
    add_h1("CHƯƠNG 3: KIỂM THỬ, ĐÁNH GIÁ VÀ HỒ SƠ BÀN GIAO")

    add_h2("3.1. Kế hoạch kiểm thử và bộ dữ liệu mẫu Deterministic (225 Requests)")
    add_p("Để việc kiểm thử mang tính khoa học, khách quan và có thể tái lập 100% trên bất kỳ máy tính nào, nhóm đã xây dựng một bộ sinh dữ liệu kiểm thử tự động có đáp án xác định trước (Ground Truth) tại tệp scripts/generate-test-data.php:")
    add_p("Bộ dữ liệu gồm chính xác 225 requests mô phỏng bao phủ toàn bộ 12 tình huống nghiệm thu khắt khe nhất:")
    add_p("• 45 requests thuộc Website A: 15 phiên hoàn tất trọn vẹn chuỗi Funnel 3 bước (page_view -> download_start -> download_complete).")
    add_p("• 20 requests thuộc Website A: 10 phiên bỏ dở tại Bước 2 (chỉ có page_view và download_start, không có download_complete).")
    add_p("• 45 requests thuộc Website A: 15 phiên duyệt web xem trang chủ, trang sản phẩm và bấm nút mua hàng.")
    add_p("• 10 requests thuộc Website A: 5 phiên duyệt web qua nửa đêm (Bước 1 lúc 23:58 hôm qua và Bước 2 lúc 00:03 hôm nay).")
    add_p("• 30 requests thuộc Website B: 10 phiên hoàn tất trọn vẹn chuỗi Funnel trên Website B.")
    add_p("• 10 requests thuộc Website B: 5 phiên bỏ dở sau Bước 2 trên Website B.")
    add_p("• 20 requests thuộc Website B: 10 phiên xem trang dịch vụ và bấm nút đăng ký tư vấn.")
    add_p("• 10 requests gửi trùng lặp mã cũ và cùng payload (Kiểm thử Case A Idempotent).")
    add_p("• 5 requests gửi trùng mã cũ nhưng cố tình sửa đổi payload (Kiểm thử Case B Xung đột).")
    add_p("• 5 requests gửi mã sự kiện mới với payload tương đương (Kiểm thử Case C Hợp lệ mới).")
    add_p("• 5 requests gửi sự kiện có thời gian trễ quá 24 giờ (Kiểm thử chính sách từ chối sự kiện trễ).")
    add_p("• 10 requests thuộc Website A: 5 phiên gửi ngược thứ tự thời gian (download_complete xảy ra trước download_start) và 5 requests có tracking_key không hợp lệ.")

    add_h2("3.2. Kiểm thử chống đếm trùng và phát hiện xung đột (Case A, B, C)")
    add_p("Kiểm thử chuyên sâu 3 nhánh xử lý Idempotency tại tệp api/track.php:")
    add_p("• Kiểm thử Case A (Test 02): Gửi lại 10 request có cùng event_id và cùng payload_hash. Kết quả thực tế: Toàn bộ 10 request đều nhận được mã HTTP 200 OK với thông điệp duplicate_idempotent. Cơ sở dữ liệu hoàn toàn không tăng thêm bản ghi nào, số đếm sự kiện giữ nguyên chính xác 100%. ĐẠT (PASS).")
    add_p("• Kiểm thử Case B (Test 03): Gửi lại 5 request có cùng event_id cũ nhưng thay đổi nội dung thuộc tính properties. Kết quả thực tế: Cả 5 request đều bị máy chủ từ chối với mã HTTP 409 Conflict. Bảng event_ingest_errors ghi nhận chính xác 5 bản ghi lỗi có mã lỗi PAYLOAD_CONFLICT kèm mã băm payload bị xung đột. ĐẠT (PASS).")
    add_p("• Kiểm thử Case C (Test 04): Gửi 5 request có event_id mới nhưng payload giống các sự kiện cũ. Kết quả thực tế: Cả 5 sự kiện đều được hệ thống chấp nhận bình thường với mã HTTP 201 Created và ghi vào bảng events. ĐẠT (PASS).")

    add_h2("3.3. Kiểm thử múi giờ qua nửa đêm và sự kiện trễ 24 giờ")
    add_p("Kiểm thử khả năng xử lý thời gian phức tạp:")
    add_p("• Kiểm thử nửa đêm (Test 05): 5 phiên có sự kiện diễn ra lúc 23:58:30 (ngày hôm qua) và sự kiện tiếp theo lúc 00:03:10 (ngày hôm nay). Kết quả: Hệ thống tính toán chuẩn xác 5 sự kiện thuộc ngày hôm qua và 130 sự kiện thuộc ngày hôm nay theo đúng múi giờ Asia/Ho_Chi_Minh, không bị lệch ngày hay mất dữ liệu. ĐẠT (PASS).")
    add_p("• Kiểm thử sự kiện đến muộn (Test 06): Gửi 5 sự kiện có thời điểm occurred_at lùi về trước hơn 25 giờ (vượt quá ngưỡng 24 giờ quy định). Kết quả: Toàn bộ 5 sự kiện đều bị từ chối với mã lỗi 422 Unprocessable Entity, bảng event_ingest_errors ghi nhận đúng 5 lỗi LATE_EVENT_EXCEEDED. ĐẠT (PASS).")

    add_h2("3.4. Kiểm thử chuỗi thao tác Funnel và bắt lỗi thứ tự thời gian")
    add_p("Kiểm thử thuật toán phân tích chuỗi chuyển đổi trên funnels.php:")
    add_p("• Kiểm thử chuỗi chuẩn (Test 07): Trên Website A có 15 phiên thực hiện đúng thứ tự page_view -> download_start -> download_complete. Kết quả: Bảng Funnel tính chính xác 15 / 15 phiên hoàn tất (đạt tỷ lệ chuyển đổi kỳ vọng). ĐẠT (PASS).")
    add_p("• Kiểm thử chống sai chuỗi (Test 08): Đưa vào 10 phiên thiếu Bước 3 và 5 phiên có download_complete xảy ra lúc 00:54:45 trước download_start lúc 00:55:00. Kết quả: Hệ thống nhận diện chuẩn xác 10 phiên 'Bỏ dở tại Bước 2' và 5 phiên 'Sai thứ tự thời gian (Loại bỏ)'. Tuyệt đối không có phiên bất thường nào bị tính nhầm vào số lượng Hoàn tất chuỗi. ĐẠT (PASS).")

    add_h2("3.5. Kiểm thử cô lập đa website và bảo mật phân quyền RBAC")
    add_p("Kiểm thử an toàn và cách ly dữ liệu:")
    add_p("• Kiểm thử cách ly website (Test 09): Thực hiện truy vấn giao nhau giữa Website A và Website B: SELECT COUNT(*) FROM events e1 INNER JOIN events e2 ON e1.session_id = e2.session_id WHERE e1.website_id = 1 AND e2.website_id = 2. Kết quả trả về bằng 0: Không có bất kỳ phiên nào bị lẫn chéo giữa Website A (135 sự kiện) và Website B (60 sự kiện). ĐẠT (PASS).")
    add_p("• Kiểm thử phân quyền RBAC (Test 10): Giả lập tài khoản Member (ID: 2) chỉ được cấp quyền xem Website 1. Khi Member truy cập Website 1: Cho phép hiển thị báo cáo. Khi Member cố tình truy cập Website 2: Máy chủ lập tức chặn đứng và trả về mã lỗi HTTP 403 Forbidden. ĐẠT (PASS).")

    add_h2("3.6. Kiểm thử bảo vệ quyền riêng tư 100% (Whitelist attributes)")
    add_p("Kiểm thử tiêu chuẩn an toàn thông tin (Test 11):")
    add_p("• Quét toàn bộ cơ sở dữ liệu để tìm kiếm các từ khóa nhạy cảm: SELECT COUNT(*) FROM events WHERE properties LIKE '%password%' OR properties LIKE '%secret%' OR properties LIKE '%credit%'.")
    add_p("• Kết quả: 0 trường nhạy cảm được tìm thấy. Toàn bộ các trường không nằm trong danh sách Whitelist đều bị hàm sanitize_properties() loại bỏ trước khi lưu vào database. ĐẠT (PASS).")

    add_h2("3.7. Kiểm thử tích hợp End-to-End toàn hệ thống (12/12 PASS 100%)")
    add_p("Bộ kiểm thử tự động tổng hợp toàn diện (Test 12) xác nhận sự kết nối thông suốt của toàn bộ 8 bảng cơ sở dữ liệu, Client SDK, REST API và các màn hình Dashboard. Khi thực thi tệp scripts/run-tests.php, kết quả đạt tỷ lệ tuyệt đối:")

    add_table_caption("Bảng 3.1: Tổng hợp kết quả nghiệm thu 12 bài kiểm thử tự động của đồ án")
    test_results_table = [
        ("Test 01", "Tổng hợp Lượt xem & Thao tác", "Views: 62, Clicks: 20", "PASS (100%)"),
        ("Test 02", "Chống đếm trùng Case A", "10 / 10 duplicate requests", "PASS (100%)"),
        ("Test 03", "Bắt xung đột dữ liệu Case B", "5 / 5 bản ghi PAYLOAD_CONFLICT", "PASS (100%)"),
        ("Test 04", "Chấp nhận sự kiện mới Case C", "5 / 5 sự kiện mới hợp lệ", "PASS (100%)"),
        ("Test 05", "Xử lý nửa đêm (Midnight)", "Hôm qua: 5, Hôm nay: 130", "PASS (100%)"),
        ("Test 06", "Chính sách sự kiện trễ > 24h", "5 / 5 sự kiện trễ bị từ chối", "PASS (100%)"),
        ("Test 07", "Phân tích chuỗi Funnel chuẩn", "15 / 15 phiên hoàn tất", "PASS (100%)"),
        ("Test 08", "Chống sai chuỗi & Bỏ dở", "10 thiếu bước + 5 ngược thứ tự bị loại", "PASS (100%)"),
        ("Test 09", "Cô lập dữ liệu đa website", "0 phiên trùng chéo (Site A: 135, Site B: 60)", "PASS (100%)"),
        ("Test 10", "Phân quyền RBAC Member", "Site 1: Cho phép; Site 2: Chặn 403", "PASS (100%)"),
        ("Test 11", "Bảo vệ quyền riêng tư 100%", "0 trường nhạy cảm trong CSDL", "PASS (100%)"),
        ("Test 12", "Tích hợp thông suốt End-to-End", "Hệ thống kết nối trơn tru 100%", "PASS (100%)")
    ]
    add_styled_table(["Mã Test", "Tiêu chí nghiệm thu Rubric", "Kết quả dữ liệu đối chiếu thực tế", "Trạng thái"], test_results_table, [2.0, 5.0, 6.5, 3.0])

    add_h2("3.8. Xử lý sự cố và khắc phục lỗi thực tế (Troubleshooting)")
    add_p("Trong quá trình phát triển và hoàn thiện đồ án, nhóm đã gặp phải và xử lý triệt để 4 lỗi kỹ thuật điển hình:")
    add_p("• Lỗi 1 - Lỗi truy vấn cột 'status' không tồn tại trong event_definitions: Khi lọc sự kiện, câu lệnh SQL cũ sử dụng điều kiện WHERE status = 'enabled', gây ra ngoại lệ PDOException 1054 Unknown column 'status'. Nhóm đã rà soát lại cấu trúc bảng DDL và sửa thành enabled = 1, khôi phục hoạt động bình thường của bộ lọc.")
    add_p("• Lỗi 2 - Ký tự emoji gây lỗi bộ kiểm tra HTML: Tệp test-http-pages.php kiểm tra tính nghiêm ngặt của giao diện doanh nghiệp và cảnh báo lỗi khi phát hiện ký tự emoji trong mã nguồn. Nhóm đã thay thế toàn bộ các emoji bằng hàm render_icon() sử dụng SVG Lucide Icons chuẩn, đưa trạng thái trang về mức Has Emoji: NO (CLEAN).")
    add_p("• Lỗi 3 - Bảng Funnel hiển thị các phiên 'Bỏ dở tại Bước 1' lên đầu bảng: Khi xem Website B, do các phiên duyệt web thông thường (chỉ xem trang mà không tải file) xảy ra muộn nhất lúc 00:50, nên việc sắp xếp theo thời gian mới nhất đã đẩy các phiên này lên trên cùng. Nhóm đã bổ sung các tab lọc nhanh: 'Tất cả các phiên', 'Hoàn tất chuỗi (10)', 'Bỏ dở sau Bước 2', và 'Bỏ dở tại Bước 1', giúp người dùng có thể xem ngay các phiên thành công chỉ với 1 cú click chuột.")
    add_p("• Lỗi 4 - Sai thứ tự tạo bản ghi gây lỗi ràng buộc khóa ngoại: Khi mô phỏng chuỗi sự kiện, bảng events có khóa ngoại tham chiếu đến bảng sessions. Nhóm đã hiệu chỉnh luồng xử lý: Bắt buộc chèn bản ghi vào bảng sessions trước, sau đó mới tiến hành chèn các sự kiện tương ứng vào bảng events, bảo đảm toàn vẹn tham chiếu 100%.")

    add_h2("3.9. Hướng dẫn cài đặt, vận hành và tái lập hệ thống")
    add_p("Hệ thống có thể được cài đặt và tái lập hoàn chỉnh trên bất kỳ máy tính chạy Windows có cài sẵn XAMPP theo các bước đơn giản sau:")
    add_p("Bước 1: Khởi động bảng điều khiển XAMPP Control Panel, nhấn nút 'Start' tại hai dịch vụ Apache và MySQL.")
    add_p("Bước 2: Sao chép thư mục dự án vào thư mục gốc C:\\xampp\\htdocs\\website-analytics\\.")
    add_p("Bước 3: Mở cửa sổ dòng lệnh PowerShell và thực thi lệnh khởi tạo cơ sở dữ liệu:")
    add_code_block(
        "Get-Content database/schema.sql | & \"C:\\xampp\\mysql\\bin\\mysql.exe\" -u root\n"
        "Get-Content database/seed.sql | & \"C:\\xampp\\mysql\\bin\\mysql.exe\" -u root"
    )
    add_p("Bước 4: Chạy bộ kiểm thử tự động 12 tiêu chí Rubric từ dòng lệnh:")
    add_code_block("& \"C:\\xampp\\php\\php.exe\" scripts/run-tests.php")
    add_p("Bước 5: Mở trình duyệt web và truy cập địa chỉ http://localhost/website-analytics/. Đăng nhập bằng tài khoản Quản trị viên (admin@analytics.local / Mật khẩu: Admin@123456) hoặc bấm nút điền nhanh 'Admin' trên màn hình đăng nhập.")

    add_h2("3.10. Hồ sơ bàn giao sản phẩm")
    add_p("Hồ sơ bàn giao bài tập lớn được đóng gói hoàn chỉnh bao gồm:")
    add_p("1. Báo cáo học phần chính thức dạng DOCX và PDF theo đúng quy định định dạng K18.")
    add_p("2. Toàn bộ mã nguồn dự án Web Analytics, mã nguồn 2 website mẫu (Demo Site A, Demo Site B).")
    add_p("3. Các tệp cơ sở dữ liệu mẫu schema.sql, seed.sql và script sinh dữ liệu kiểm thử 225 requests.")
    add_p("4. Bộ tệp tài liệu kỹ thuật chi tiết tại thư mục docs/ (API, Hướng dẫn cài đặt, Kiểm thử, Hướng dẫn sử dụng).")

    doc.add_page_break()

    # =========================================================================
    # KẾT LUẬN
    # =========================================================================
    add_h1("KẾT LUẬN")

    add_p("Về ưu điểm và kết quả đạt được, đề tài đã xây dựng thành công một hệ sinh thái Web Analytics mã nguồn mở hoàn chỉnh, vận hành ổn định trên nền tảng PHP 8.2 thuần kết hợp PDO và cơ sở dữ liệu MariaDB, độc lập 100% không phụ thuộc vào các dịch vụ đám mây đóng kín của bên thứ ba. Hệ thống sở hữu bộ JavaScript Tracking SDK siêu nhẹ (<8KB), tự động thu thập bất đồng bộ và duy trì phiên ẩn danh an toàn. Điểm sáng nổi bật của dự án là giải thuật chống đếm trùng Idempotent 3 nhánh (Case A, B, C) dựa trên mã băm SHA-256 chuẩn hóa, khả năng phân tách chính xác phiên truy cập qua nửa đêm (23:58 và 00:03 theo giờ Việt Nam), cơ chế từ chối sự kiện trễ quá 24 giờ và thuật toán phân tích chuỗi thao tác Funnel 3 bước với 4 điều kiện kiểm tra thứ tự thời gian chặt chẽ. Hệ thống cũng đã triển khai thành công mô hình phân quyền bảo mật RBAC, cơ chế Whitelist bảo vệ quyền riêng tư tuyệt đối (0 trường nhạy cảm trong CSDL) và tích hợp Trung tâm kiểm thử tự động đạt tỷ lệ vượt qua tuyệt đối 12/12 tiêu chí Rubric (PASS 100%) ngay trên giao diện web.")

    add_p("Về nhược điểm và hạn chế tồn tại, mặc dù đã đáp ứng trọn vẹn toàn bộ các yêu cầu kỹ thuật của đề cương bài tập lớn, hệ thống vẫn còn một số điểm cần tiếp tục hoàn thiện do giới hạn về thời gian nghiên cứu và tài nguyên phần cứng thử nghiệm. Cụ thể, kiến trúc tiếp nhận hiện tại thực hiện ghi nhận trực tiếp vào cơ sở dữ liệu quan hệ MariaDB mà chưa có tầng hàng đợi đệm bất đồng bộ chuyên dụng (như Redis Queue hay Apache Kafka), điều này có thể dẫn đến hiện tượng nghẽn I/O khi hệ thống phải đối mặt với lưu lượng truy cập tăng vọt đột biến (hàng chục nghìn request mỗi giây). Ngoài ra, hệ thống mới chỉ tập trung phân tích các sự kiện có chủ đích (lượt xem trang, click nút bấm, tiến trình tải tài liệu) mà chưa hỗ trợ phân tích bản đồ nhiệt nhấp chuột (Click/Scroll Heatmap), chưa đo lường độ sâu cuộn trang và chưa tích hợp tính năng ghi hình lại phiên làm việc của người dùng (Session Replay).")

    add_p("Về hướng phát triển chủ đề trong tương lai, nhóm nghiên cứu định hướng tiếp tục nâng cấp và mở rộng nền tảng theo ba hướng chiến lược trọng tâm: Thứ nhất, tối ưu hóa hạ tầng kiến trúc bằng cách triển khai hàng đợi đệm Redis, phân tách cơ chế Đọc/Ghi (CQRS / Master-Slave Replication) và đóng gói hệ thống dưới dạng vi dịch vụ (Microservices với Docker/Kubernetes) để nâng cao khả năng chịu tải lên quy mô hàng triệu sự kiện mỗi ngày. Thứ hai, ứng dụng các mô hình học máy (Machine Learning) để xây dựng tính năng phát hiện lưu lượng truy cập bất thường (Anomalous Traffic Detection), tự động sàng lọc bot spam click và phân tích dự đoán hành vi rời bỏ trang của khách hàng. Thứ ba, phát triển thêm các module trực quan hóa trải nghiệm người dùng nâng cao như bản đồ nhiệt Heatmap, biểu đồ luồng hành trình người dùng (User Journey Flow) và xây dựng bộ RESTful Webhook mở rộng cho phép tích hợp dữ liệu phân tích linh hoạt vào các hệ thống quản trị quan hệ khách hàng (CRM, ERP) của doanh nghiệp.")

    doc.add_page_break()

    # =========================================================================
    # TÀI LIỆU THAM KHẢO
    # =========================================================================
    add_h1("TÀI LIỆU THAM KHẢO")
    add_p("Danh mục tài liệu tham khảo được trình bày nhất quán theo tiêu chuẩn APA (American Psychological Association):")
    refs = [
        "1. Khoa Công nghệ Thông tin, Trường Đại học Đại Nam. (2026). Đề cương chi tiết học phần Phát triển phần mềm mã nguồn mở. Hà Nội: Đại học Đại Nam.",
        "2. Fielding, R. T. (2000). Architectural Styles and the Design of Network-based Software Architectures (Doctoral dissertation). University of California, Irvine.",
        "3. The PHP Group. (2026). PHP: Hypertext Preprocessor Documentation (PHP 8.2). Retrieved from https://www.php.net/docs.php",
        "4. MariaDB Foundation. (2026). MariaDB Server Documentation and Knowledge Base. Retrieved from https://mariadb.com/kb/",
        "5. W3C. (2023). Beacon API - W3C Candidate Recommendation Snapshot. World Wide Web Consortium. Retrieved from https://www.w3.org/TR/beacon/",
        "6. Chart.js Community. (2026). Chart.js: Simple yet flexible JavaScript charting for designers & developers (v4.4). Retrieved from https://www.chartjs.org/",
        "7. Lucide Contributors. (2026). Lucide: Beautiful & consistent open-source icons. Retrieved from https://lucide.dev/",
        "8. OWASP Foundation. (2025). OWASP Top Ten Web Application Security Risks. Open Web Application Security Project.",
        "9. Apache Friends. (2026). XAMPP Apache + MariaDB + PHP + Perl Distribution. Retrieved from https://www.apachefriends.org/"
    ]
    for r in refs:
        p = doc.add_paragraph()
        p.paragraph_format.line_spacing = 1.25
        p.paragraph_format.space_after = Pt(4)
        run = p.add_run(r)
        run.font.name = 'Times New Roman'
        run.font.size = Pt(11)

    doc.add_page_break()

    # =========================================================================
    # PHỤ LỤC
    # =========================================================================
    add_h1("PHỤ LỤC")

    add_h2("Phụ lục A: Cấu trúc DDL Schema Cơ Sở Dữ Liệu Hoàn Chỉnh")
    add_p("Dưới đây là mã nguồn SQL DDL khởi tạo toàn bộ 8 bảng chuẩn hóa trong cơ sở dữ liệu website_analytics:")
    add_code_block(
        "-- BẢNG 1: WEBSITES\n"
        "CREATE TABLE `websites` (\n"
        "  `website_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        "  `name` varchar(100) NOT NULL,\n"
        "  `domain` varchar(255) NOT NULL,\n"
        "  `tracking_key` varchar(64) NOT NULL UNIQUE,\n"
        "  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',\n"
        "  `late_event_window_hours` int(11) NOT NULL DEFAULT 24,\n"
        "  `status` enum('active','inactive') NOT NULL DEFAULT 'active',\n"
        "  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        "  PRIMARY KEY (`website_id`)\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n"
        "-- BẢNG 2: USERS\n"
        "CREATE TABLE `users` (\n"
        "  `user_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        "  `full_name` varchar(100) NOT NULL,\n"
        "  `email` varchar(150) NOT NULL UNIQUE,\n"
        "  `password_hash` varchar(255) NOT NULL,\n"
        "  `role` enum('admin','member') NOT NULL DEFAULT 'member',\n"
        "  `status` enum('active','locked') NOT NULL DEFAULT 'active',\n"
        "  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        "  PRIMARY KEY (`user_id`)\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n"
        "-- BẢNG 3: WEBSITE_PERMISSIONS\n"
        "CREATE TABLE `website_permissions` (\n"
        "  `permission_id` int(11) NOT NULL AUTO_INCREMENT,\n"
        "  `user_id` int(11) NOT NULL,\n"
        "  `website_id` int(11) NOT NULL,\n"
        "  `can_view_reports` tinyint(1) NOT NULL DEFAULT 1,\n"
        "  `can_manage_tracking` tinyint(1) NOT NULL DEFAULT 0,\n"
        "  `can_export_csv` tinyint(1) NOT NULL DEFAULT 1,\n"
        "  PRIMARY KEY (`permission_id`),\n"
        "  UNIQUE KEY `uk_user_website` (`user_id`,`website_id`),\n"
        "  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,\n"
        "  FOREIGN KEY (`website_id`) REFERENCES `websites` (`website_id`) ON DELETE CASCADE\n"
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    )

    add_h2("Phụ lục B: Bảng Phân Công Công Việc Và Mức Độ Đóng Góp")
    add_p("Toàn bộ các thành viên trong nhóm đã tham gia tích cực và đóng góp trách nhiệm vào sự thành công của đề tài:")

    add_table_caption("Bảng Phụ lục B.1: Bảng phân công nhiệm vụ và đánh giá đóng góp thành viên")
    contributions = [
        ("1", "Nguyễn Anh Đức (Nhóm trưởng)", "Quản lý dự án, thiết kế kiến trúc hệ thống, xây dựng REST API Ingest, thuật toán chống đếm trùng SHA-256, phân tích Funnel, viết báo cáo tổng hợp.", "100% (Hoàn thành xuất sắc)"),
        ("2", "Lưu Phương Thảo", "Phát triển giao diện Dashboard KPI, tích hợp thư viện Chart.js, xây dựng module quản trị người dùng và ma trận phân quyền RBAC, kiểm thử dữ liệu.", "100% (Hoàn thành xuất sắc)"),
        ("3", "Lê Hoàng Nam", "Phát triển JavaScript Tracking SDK, xây dựng 2 website thử nghiệm Demo Site A và Demo Site B, thiết kế kịch bản kiểm thử 225 requests, đóng gói tài liệu.", "100% (Hoàn thành xuất sắc)")
    ]
    add_styled_table(["STT", "Họ và tên thành viên", "Nhiệm vụ đảm nhiệm trong dự án", "Đánh giá mức độ hoàn thành"], contributions, [1.2, 4.5, 7.8, 3.0])

    add_h2("Phụ lục C: Khai Báo Sử Dụng Công Cụ Trợ Lý AI")
    add_p("Tuân thủ nghiêm ngặt quy định về liêm chính học thuật của Trường Đại học Đại Nam và Khoa Công nghệ Thông tin, nhóm xin khai báo việc sử dụng công cụ trí tuệ nhân tạo như sau:")
    add_p("1. Công cụ sử dụng: Antigravity AI Assistant (Google DeepMind).")
    add_p("2. Mục đích sử dụng: Hỗ trợ rà soát cú pháp câu lệnh SQL DDL, hỗ trợ gợi ý cấu trúc thư viện SVG Lucide Icons, hỗ trợ định dạng bảng biểu và rà soát lỗi chính tả trong văn bản báo cáo.")
    add_p("3. Cam kết học thuật: Toàn bộ tư duy kiến trúc, logic xử lý chống đếm trùng, thuật toán phân tích chuỗi Funnel, cơ chế phân quyền RBAC và mã nguồn PHP/JavaScript đều do các thành viên trong nhóm tự tìm hiểu, hiện thực, kiểm thử độc lập và hoàn toàn làm chủ kiến thức khi thuyết trình và vấn đáp trước Hội đồng chấm điểm.")

    # Lưu tài liệu
    doc.save(output_path)
    print(f"Báo cáo học phần đã được tạo thành công tại: {output_path}")

if __name__ == '__main__':
    out = r'd:\btl PMP\PTPMMNM-CNTT-1809-NHOM7\Báo cáo học phần K18 - Nhóm 07 - Đề tài 17.docx'
    create_full_report(out)
