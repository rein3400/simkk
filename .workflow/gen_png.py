import struct, zlib, base64
def png(width, height, color=(255, 100, 100)):
    sig = b'\x89PNG\r\n\x1a\n'
    def chunk(typ, data):
        return struct.pack('>I', len(data)) + typ + data + struct.pack('>I', zlib.crc32(typ + data) & 0xffffffff)
    ihdr = struct.pack('>IIBBBBB', width, height, 8, 2, 0, 0, 0)
    raw = b''
    for _ in range(height):
        raw += b'\x00' + bytes(color * width)
    idat = zlib.compress(raw)
    return sig + chunk(b'IHDR', ihdr) + chunk(b'IDAT', idat) + chunk(b'IEND', b'')
png_data = png(100, 100)
print(base64.b64encode(png_data).decode())
