import os
import sys
from PIL import Image

def procesar_imagenes(directorio_origen):
    # Verificar si el directorio existe
    if not os.path.exists(directorio_origen):
        print(f"Error: La ruta '{directorio_origen}' no existe.")
        return

    # Crear la carpeta de destino img_webp
    directorio_destino = os.path.join(directorio_origen, 'img_webp')
    os.makedirs(directorio_destino, exist_ok=True)
    
    # Extensiones de imagen soportadas
    extensiones_validas = ('.jpg', '.jpeg', '.png', '.webp', '.bmp', '.tiff')
    
    archivos_procesados = 0

    for nombre_archivo in os.listdir(directorio_origen):
        # Ignorar archivos ocultos o carpetas
        if nombre_archivo.startswith('.'):
            continue
            
        ruta_completa = os.path.join(directorio_origen, nombre_archivo)
        if os.path.isdir(ruta_completa):
            continue
            
        ext = os.path.splitext(nombre_archivo)[1].lower()
        if ext not in extensiones_validas:
            continue
            
        try:
            with Image.open(ruta_completa) as img:
                formato_original = img.format
                modo_original = img.mode
                
                # 1. Manejo del canal alfa y fondo blanco
                # Si la imagen tiene transparencia (RGBA, LA, etc.) la conservamos.
                if modo_original in ('RGBA', 'LA') or (modo_original == 'P' and 'transparency' in img.info):
                    img = img.convert('RGBA')
                else:
                    # Si es RGB o JPEG, nos aseguramos de que sea RGB limpio
                    # Si se requiere fondo blanco sobre una imagen sin definir, se convierte a RGB
                    img = img.convert('RGB')
                
                # 2. Recorte cuadrado exacto (1:1) - Recortando por el centro
                ancho, alto = img.size
                lado_menor = min(ancho, alto)
                
                izq = (ancho - lado_menor) // 2
                sup = (alto - lado_menor) // 2
                der = izq + lado_menor
                inf = sup + lado_menor
                
                img_recortada = img.crop((izq, sup, der, inf))
                
                # 3. Redimensionamiento
                # Si la imagen es más grande de 1000x1000, forzamos a 1000x1000
                # Si es más pequeña, se queda con su tamaño 1:1 original
                if lado_menor > 1000:
                    img_final = img_recortada.resize((1000, 1000), Image.Resampling.LANCZOS)
                else:
                    img_final = img_recortada
                    
                # 4. Guardar como WebP con 80% de calidad
                nombre_base = os.path.splitext(nombre_archivo)[0]
                ruta_salida = os.path.join(directorio_destino, f"{nombre_base}.webp")
                
                img_final.save(ruta_salida, format='WEBP', quality=80)
                print(f"✅ Procesada: {nombre_archivo} -> {nombre_base}.webp ({img_final.size[0]}x{img_final.size[1]}px)")
                archivos_procesados += 1
                
        except Exception as e:
            print(f"❌ Error al procesar '{nombre_archivo}': {e}")
            
    print(f"\nProceso terminado. Se procesaron {archivos_procesados} imágenes.")
    print(f"Las imágenes están en: {directorio_destino}")

if __name__ == '__main__':
    # Permite ejecutar el script pasando la ruta como argumento
    # Ejemplo: python procesador_imagenes.py /ruta/a/mis/imagenes
    if len(sys.argv) > 1:
        ruta_input = sys.argv[1]
        procesar_imagenes(ruta_input)
    else:
        print("Uso: python procesador_imagenes.py <ruta_del_directorio>")
        print("Ejemplo: python procesador_imagenes.py ./imagenes_raw")