#!/usr/bin/env bash
# Chỉ dùng cho học tập cục bộ. Script không cài gói, không xóa dữ liệu.
set -u
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)" || exit 2
cd "$root" || exit 2

port="8200"
port_alpha="8201"
port_beta="8202"

usage() {
  printf 'Cách dùng: bash lab.sh check | run | test | seed | db-config | sql\n'
  printf '  check:     Kiểm tra môi trường\n'
  printf '  run:       Chạy dashboard (http://127.0.0.1:%s)\n' "$port"
  printf '  demo:      Chạy 2 website thử (port %s và %s)\n' "$port_alpha" "$port_beta"
  printf '  test:      Chạy phép thử đơn vị (không cần MySQL)\n'
  printf '  integrate: Chạy phép thử tích hợp (cần MySQL)\n'
  printf '  seed:      Tạo dữ liệu mẫu (~200 sự kiện)\n'
  printf '  db-config: Cấu hình kết nối MySQL\n'
  printf '  sql:       Áp dụng schema SQL\n'
  printf 'Dùng Ctrl+C để dừng server.\n'
}

# Tự động nhận diện PHP của XAMPP (có sẵn extension pdo_mysql)
PHP_BIN="php"
if [ -x "/mnt/c/xampp/php/php.exe" ]; then
  PHP_BIN="/mnt/c/xampp/php/php.exe"
elif [ -x "C:/xampp/php/php.exe" ]; then
  PHP_BIN="C:/xampp/php/php.exe"
elif [ -x "/c/xampp/php/php.exe" ]; then
  PHP_BIN="/c/xampp/php/php.exe"
fi

need_php() {
  command -v "$PHP_BIN" >/dev/null 2>&1 || { echo 'THIEU_PHP'; return 2; }
  "$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || { echo 'CAN_PHP_8_1_TRO_LEN'; return 2; }
}

check_env() {
  need_php || return 2
  command -v git >/dev/null 2>&1 || { echo 'THIEU_GIT'; return 2; }
  "$PHP_BIN" tools/db-check.php 2>/dev/null && echo 'DATABASE=OK' || echo 'DATABASE=CHUA_CAU_HINH (chay: bash lab.sh db-config)'
  printf 'MOI_TRUONG=SAN_SANG\n'
}

case "${1:-help}" in
  check)
    check_env ;;

  run)
    need_php || exit 2
    mkdir -p .local
    echo "THU_MUC=$root"
    echo "DIA_CHI=http://127.0.0.1:$port"
    echo "Giữ terminal này mở. Nhấn Ctrl+C để dừng."
    "$PHP_BIN" -S "127.0.0.1:$port" -t public ;;

  demo)
    need_php || exit 2
    echo "Website Alpha: http://127.0.0.1:$port_alpha"
    echo "Website Beta:  http://127.0.0.1:$port_beta"
    echo "Chạy 2 server song song (Ctrl+C để dừng)..."
    "$PHP_BIN" -S "127.0.0.1:$port_alpha" -t demo-sites/site-alpha &
    PID_ALPHA=$!
    "$PHP_BIN" -S "127.0.0.1:$port_beta"  -t demo-sites/site-beta &
    PID_BETA=$!
    trap "kill $PID_ALPHA $PID_BETA 2>/dev/null" INT TERM
    wait ;;

  test)
    need_php || exit 2
    echo "=== Phép thử đơn vị (SQLite in-memory) ==="
    "$PHP_BIN" tests/run.php ;;

  integrate)
    need_php || exit 2
    echo "=== Phép thử tích hợp (MySQL) ==="
    "$PHP_BIN" tests/integration.php ;;

  seed)
    need_php || exit 2
    echo "=== Tạo dữ liệu mẫu ==="
    "$PHP_BIN" fixtures/seed.php ;;

  db-config)
    need_php || exit 2
    "$PHP_BIN" tools/db-config.php ;;

  sql)
    need_php || exit 2
    "$PHP_BIN" tools/sql.php ;;

  help|-h|--help) usage ;;
  *) usage; exit 2 ;;
esac
