# Gestion des formats d'image dans le thème DNC

Ce thème vous permet d'accepter des formats d'image avancés à l'upload, et de convertir certains automatiquement en JPG pour une meilleure compatibilité.

## 📦 Formats supportés

| Format       | Description                         | Upload autorisé | Converti en JPG | Nécessite Imagick |
|--------------|-------------------------------------|------------------|------------------|-------------------|
| **HEIC/HEIF** | Format iPhone récent                | ✅ (option)       | ✅               | ✅ + libheif       |
| **TIFF**      | Images brutes (.tif/.tiff)          | ✅ (option)       | ✅               | ✅                |
| **WebP**      | Format optimisé moderne             | ✅ (option)       | ❌               | ❌                |
| **AVIF**      | Format ultra-compressé récent       | ✅ (option)       | ❌               | ❌                |
| **JPEG XL**   | Nouveau format JPEG amélioré        | ✅ (option)       | ❌               | ❌                |

## 🛠️ Pré-requis serveur

- **Imagick** est requis pour convertir HEIC/HEIF et TIFF en JPG.
- Pour le support HEIC, **libheif** doit être activé côté serveur.
- AVIF et JPEG XL nécessitent un **serveur récent** (PHP 8.1+, GD ou Imagick à jour).

## ⚙️ Activer les formats

Dans l’administration WordPress :  
**Apparence > Réglages du thème > Options générales**  
→ Cochez les formats que vous souhaitez autoriser.

- HEIC/HEIF : autorise et convertit les images iPhone
- TIFF : autorise et convertit les .tif/.tiff en JPG
- WebP, AVIF, JPEG XL : autorisés, non convertis

## 📤 Comportement à l’upload

- Si la conversion est activée : le fichier est transformé en `.jpg` au moment de l’envoi.
- Le nom du fichier est aussi converti (ex. `image.heic` → `image.jpg`).

## ⚠️ Limitations

- Certains navigateurs ne supportent pas encore AVIF ou JXL.
- WordPress n’affiche pas nativement tous ces formats en galerie/media.
- Les conversions dépendent du serveur : si Imagick est absent, un message d’erreur sera affiché en admin.

## 🔧 Dépannage

Si la conversion ne fonctionne pas :
- Vérifiez que `Imagick` est installé (admin > message d'erreur).
- Pour HEIC : `libheif` doit être activé dans Imagick.
- Pour TIFF : Imagick doit inclure le support TIFF dans ses formats.

## 📁 Localisation

Les réglages sont centralisés dans :
- `theme-options.php` → section `section_general`
- `images-optimizer.php` → logique d’upload et conversion
