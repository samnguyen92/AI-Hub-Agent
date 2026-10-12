"""Tools for importing and querying SEO / analytics tabular reports (GSC, GA4)."""

import csv
import json
import os
from typing import Any, Dict, List
from app.services.workspace import workspace_service
from app.tools.base import BaseTool, ToolContext, tool_registry
from app.tools.file_tools import safe_resolve_path


class ImportReportTool(BaseTool):
    """Tool to parse and normalize Search Console or GA4 CSV files."""

    name = "import_report"
    description = "Nhập và chuẩn hóa file CSV từ Google Search Console hoặc Google Analytics 4."
    parameters_schema = {
        "type": "object",
        "properties": {
            "csv_path": {"type": "string", "description": "Đường dẫn file CSV trong workspace"},
            "report_name": {"type": "string", "description": "Tên định danh báo cáo (vd: gsc-performance)"},
        },
        "required": ["csv_path", "report_name"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        csv_rel = kwargs["csv_path"]
        rep_name = kwargs["report_name"]

        try:
            full_path = safe_resolve_path(context.workspace_path, csv_rel)
            if not os.path.exists(full_path):
                return {"error": f"Không tìm thấy file: {csv_rel}"}

            rows: List[Dict[str, Any]] = []
            with open(full_path, "r", encoding="utf-8-sig") as f:
                reader = csv.DictReader(f)
                for r in reader:
                    cleaned_row = {}
                    for k, v in r.items():
                        if not k:
                            continue
                        key = k.strip().lower().replace(" ", "_")
                        # Try to cast numeric values
                        val: Any = v.strip()
                        if val.replace(".", "", 1).isdigit():
                            val = float(val) if "." in val else int(val)
                        elif val.endswith("%") and val[:-1].replace(".", "", 1).isdigit():
                            val = float(val[:-1])
                        cleaned_row[key] = val
                    rows.append(cleaned_row)

            # Save normalized JSON
            out_rel = f"reports/{rep_name}.json"
            out_full = safe_resolve_path(context.workspace_path, out_rel)
            os.makedirs(os.path.dirname(out_full), exist_ok=True)
            with open(out_full, "w", encoding="utf-8") as out_f:
                json.dump(rows, out_f, ensure_ascii=False, indent=2)

            workspace_service.commit_step(context.project_id, f"report: import {rep_name}")
            return {
                "report_name": rep_name,
                "rows_count": len(rows),
                "columns": list(rows[0].keys()) if rows else [],
                "saved_path": out_rel,
            }
        except Exception as exc:
            return {"error": f"Lỗi nhập báo cáo: {str(exc)}"}


class QueryReportTool(BaseTool):
    """Tool to filter and sort normalized tabular report data."""

    name = "query_report"
    description = "Lọc, tìm kiếm và xếp hạng dữ liệu từ báo cáo đã import."
    parameters_schema = {
        "type": "object",
        "properties": {
            "report_name": {"type": "string", "description": "Tên báo cáo cần truy vấn"},
            "sort_by": {"type": "string", "description": "Cột cần sắp xếp (vd: 'impressions', 'clicks', 'ctr')"},
            "descending": {"type": "boolean", "description": "Sắp xếp giảm dần (mặc định true)"},
            "limit": {"type": "integer", "description": "Số dòng kết quả tối đa (mặc định 10)"},
        },
        "required": ["report_name"],
    }

    async def execute(self, context: ToolContext, **kwargs: Any) -> Dict[str, Any]:
        rep_name = kwargs["report_name"]
        sort_by = kwargs.get("sort_by")
        desc = kwargs.get("descending", True)
        limit = min(kwargs.get("limit", 10), 50)

        try:
            rel_path = f"reports/{rep_name}.json"
            full_path = safe_resolve_path(context.workspace_path, rel_path)
            if not os.path.exists(full_path):
                return {"error": f"Chưa có dữ liệu báo cáo: {rep_name}. Hãy chạy import_report trước."}

            with open(full_path, "r", encoding="utf-8") as f:
                data: List[Dict[str, Any]] = json.load(f)

            if sort_by:
                data = sorted(
                    data,
                    key=lambda x: x.get(sort_by, 0) if isinstance(x.get(sort_by), (int, float)) else 0,
                    reverse=desc,
                )

            return {
                "report_name": rep_name,
                "total_rows": len(data),
                "results": data[:limit],
            }
        except Exception as exc:
            return {"error": f"Lỗi truy vấn báo cáo: {str(exc)}"}


# Register tools
tool_registry.register(ImportReportTool())
tool_registry.register(QueryReportTool())
