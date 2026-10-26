#!/usr/bin/env bash
# ==============================================================================
# Script cài đặt & triển khai Align AI Hub trên Hostinger VPS (KVM 2)
# Hỗ trợ: Ubuntu / Debian
# Cách dùng: chmod +x deploy-vps.sh && ./deploy-vps.sh
# ==============================================================================

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}======================================================${NC}"
echo -e "${BLUE}      ALIGN AI HUB - KHỞI TẠO HỆ THỐNG TRÊN VPS      ${NC}"
echo -e "${BLUE}======================================================${NC}"

# 1. Kiểm tra & cài đặt Docker nếu chưa có
if ! command -v docker &> /dev/null; then
    echo -e "\n${YELLOW}[1/4] Docker chưa được cài đặt. Đang tiến hành cài đặt Docker tự động...${NC}"
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm -f get-docker.sh
    systemctl enable docker
    systemctl start docker
    echo -e "${GREEN}✓ Đã cài đặt Docker thành công.${NC}"
else
    echo -e "\n${GREEN}[1/4] Docker đã có sẵn: $(docker --version)${NC}"
fi

# 2. Khởi tạo file .env nếu chưa tồn tại
if [ ! -f ".env" ]; then
    echo -e "\n${YELLOW}[2/4] Chưa tìm thấy file .env. Đang tự động tạo từ mẫu production...${NC}"
    cp .env.production.example .env

    # Tự động sinh khóa ngẫu nhiên an toàn (32 ký tự)
    DB_PASS=$(openssl rand -hex 16)
    AUTH_SECRET=$(openssl rand -hex 24)
    ADMIN_TOKEN=$(openssl rand -hex 24)
    SANDBOX_SECRET=$(openssl rand -hex 24)

    sed -i "s/replace_with_a_secure_db_password/$DB_PASS/g" .env
    sed -i "s/replace_with_a_random_secret_at_least_32_chars/$AUTH_SECRET/g" .env
    sed -i "s/replace_with_a_long_random_admin_token/$ADMIN_TOKEN/g" .env
    sed -i "s/replace_with_a_random_sandbox_secret_at_least_32_chars/$SANDBOX_SECRET/g" .env

    echo -e "${GREEN}✓ Đã tự sinh các khóa bảo mật ngẫu nhiên trong file .env.${NC}"
    echo -e "${YELLOW}⚠️  LƯU Ý: Vui lòng mở file .env để điền OPENROUTER_API_KEY nếu chưa có.${NC}"
else
    echo -e "\n${GREEN}[2/4] File .env đã tồn tại.${NC}"
fi

# 3. Build & Khởi chạy các container
echo -e "\n${YELLOW}[3/4] Đang build và khởi chạy toàn bộ dịch vụ (Web, API, Worker, Sandbox, Postgres)...${NC}"
docker compose -f docker-compose.prod.yml up -d --build

# 4. Kiểm tra trạng thái
echo -e "\n${YELLOW}[4/4] Đang kiểm tra trạng thái hoạt động...${NC}"
sleep 10
docker compose -f docker-compose.prod.yml ps

PUBLIC_IP=$(curl -s -4 ifconfig.me || curl -s icanhazip.com || echo "IP_VPS_CỦA_BẠN")

echo -e "\n${GREEN}======================================================${NC}"
echo -e "${GREEN}           TRIỂN KHAI HOÀN TẤT THÀNH CÔNG!            ${NC}"
echo -e "${GREEN}======================================================${NC}"
echo -e "Truy cập giao diện Hub ngay tại: ${BLUE}http://${PUBLIC_IP}:3100${NC} (hoặc ${BLUE}https://hub.align.vn${NC} qua NPM)"
echo -e "Tài khoản đăng nhập mặc định:"
echo -e "  - Email:    ${YELLOW}dev@align.vn${NC}"
echo -e "  - Password: ${YELLOW}admin123456${NC} (hoặc theo mật khẩu trong .env)"
echo -e "\nCác lệnh hữu ích khi quản trị VPS:"
echo -e "  - Xem log:         ${YELLOW}docker compose -f docker-compose.prod.yml logs -f${NC}"
echo -e "  - Khởi động lại:   ${YELLOW}docker compose -f docker-compose.prod.yml restart${NC}"
echo -e "  - Tắt hệ thống:    ${YELLOW}docker compose -f docker-compose.prod.yml down${NC}"
echo -e "======================================================\n"
