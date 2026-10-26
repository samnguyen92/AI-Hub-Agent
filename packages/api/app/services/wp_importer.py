"""WordPress Importer Service for Align Theme HTML ZIP packages."""

import asyncio
import json
import os
from typing import Any, Dict, List, Optional, Tuple


class WordPressImporterService:
    """Automates HTML package import into Align theme using WP-CLI and internal batch runner."""

    @staticmethod
    async def run_cmd(cmd: List[str], timeout: int = 180) -> Tuple[int, str, str]:
        """Execute a shell command asynchronously."""
        proc = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        try:
            stdout, stderr = await asyncio.wait_for(proc.communicate(), timeout=timeout)
            return (
                proc.returncode or 0,
                stdout.decode("utf-8", errors="replace"),
                stderr.decode("utf-8", errors="replace"),
            )
        except asyncio.TimeoutError:
            proc.kill()
            return (-1, "", "Import execution timed out.")

    def _generate_import_php_script(self) -> str:
        """Generate PHP script to execute Align Theme ZIP Importer programmatically."""
        return (
            "<?php\n"
            "define('WP_USE_THEMES', false);\n"
            "require_once '/var/www/html/wp-load.php';\n"
            "$importer_file = get_template_directory() . '/core/includes/admin/importer/class-align-zip-importer.php';\n"
            "if (!file_exists($importer_file)) {\n"
            "    echo json_encode(['success' => false, 'error' => 'Align Importer not found at ' . $importer_file]);\n"
            "    exit(1);\n"
            "}\n"
            "require_once $importer_file;\n"
            "$zip_path = '/tmp/site-package.zip';\n"
            "$res1 = Align_Zip_Batch_Runner::init_session($zip_path, 'site-package.zip');\n"
            "if (empty($res1['success'])) {\n"
            "    echo json_encode(['success' => false, 'error' => $res1['message'] ?? 'Init failed']);\n"
            "    exit(1);\n"
            "}\n"
            "$sid = $res1['session_id'];\n"
            "Align_Zip_Batch_Runner::reset_and_register_assets($sid, ['overwrite' => true]);\n"
            "Align_Zip_Batch_Runner::process_parts_and_blocks($sid, ['overwrite' => true, 'minify' => false]);\n"
            "$catalog = Align_Zip_Batch_Runner::load_catalog($sid);\n"
            "$pages = $catalog['pages'] ?? [];\n"
            "$total = count($pages);\n"
            "Align_Zip_Batch_Runner::process_pages_batch($sid, 0, max(1, $total), ['overwrite' => true, 'minify' => false]);\n"
            "$final = Align_Zip_Batch_Runner::finalize_session($sid);\n"
            "echo json_encode([\n"
            "    'success' => true,\n"
            "    'session_id' => $sid,\n"
            "    'pages_imported' => $total,\n"
            "    'final' => $final,\n"
            "]);\n"
        )

    async def import_zip_package(
        self, container_name: str, zip_path: str
    ) -> Dict[str, Any]:
        """Copy HTML zip to WordPress container and run Align theme importer."""
        if not os.path.exists(zip_path):
            return {"success": False, "error": f"ZIP file not found at {zip_path}"}

        # 1. Copy ZIP into container
        cp_cmd = ["docker", "cp", zip_path, f"{container_name}:/tmp/site-package.zip"]
        code, _, err = await self.run_cmd(cp_cmd, timeout=60)
        if code != 0:
            return {"success": False, "error": f"Failed to copy ZIP: {err}"}

        # 2. Write runner PHP script into container
        php_code = self._generate_import_php_script()
        write_cmd = [
            "docker", "exec", "-i", container_name,
            "sh", "-c", "cat > /tmp/run_align_import.php",
        ]
        proc = await asyncio.create_subprocess_exec(
            *write_cmd,
            stdin=asyncio.subprocess.PIPE,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
        )
        await proc.communicate(input=php_code.encode("utf-8"))

        # 3. Execute runner PHP script as www-data
        exec_cmd = ["docker", "exec", "-u", "www-data", container_name, "php", "/tmp/run_align_import.php"]
        code, stdout, stderr = await self.run_cmd(exec_cmd, timeout=180)

        # 4. Clean up /tmp files inside container
        await self.run_cmd(["docker", "exec", container_name, "rm", "-f", "/tmp/site-package.zip", "/tmp/run_align_import.php"])

        if code != 0:
            return {
                "success": False,
                "error": f"Import script failed with code {code}: {stderr or stdout}",
            }

        try:
            return json.loads(stdout.strip())
        except Exception:
            return {
                "success": True,
                "raw_output": stdout,
                "message": "Import finished (non-JSON response).",
            }


wp_importer = WordPressImporterService()
