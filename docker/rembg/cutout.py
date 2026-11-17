"""One CPU cutout per process. Images enter on stdin and leave as PNG on stdout."""

import hashlib
import io
import os
from pathlib import Path
import sys

os.environ.setdefault("NUMBA_DISABLE_JIT", "1")
os.environ.setdefault("OMP_NUM_THREADS", "1")
os.environ.setdefault("OPENBLAS_NUM_THREADS", "1")


def main():
    model = Path(sys.argv[1]).resolve(strict=True)
    if model.name != "u2netp.onnx" or hashlib.md5(model.read_bytes()).hexdigest() != "8e83ca70e441ab06c318d82300c84806":
        raise ValueError("Invalid bundled cutout model")
    os.environ["U2NET_HOME"] = str(model.parent)

    from PIL import Image
    import onnxruntime as ort
    from rembg import remove
    from rembg.sessions.u2netp import U2netpSession

    Image.MAX_IMAGE_PIXELS = 1_440_000
    binary = sys.stdin.buffer.read(8 * 1024 * 1024 + 1)
    if len(binary) > 8 * 1024 * 1024:
        raise ValueError("Input exceeds cutout limit")
    source = Image.open(io.BytesIO(binary))
    if max(source.size) > 1200 or source.width * source.height > 1_440_000:
        raise ValueError("Input dimensions exceed cutout limit")
    source = source.convert("RGB")
    options = ort.SessionOptions()
    options.intra_op_num_threads = 1
    options.inter_op_num_threads = 1
    options.enable_cpu_mem_arena = False
    options.enable_mem_pattern = False
    session = U2netpSession("u2netp", options, providers=["CPUExecutionProvider"])
    result = remove(source, session=session, alpha_matting=False, putalpha=True)
    result.save(sys.stdout.buffer, format="PNG")


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(type(error).__name__, file=sys.stderr)
        sys.exit(1)
