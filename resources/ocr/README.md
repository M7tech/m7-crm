# Bundled Sorani OCR model

`sorani.traineddata.gz` is a gzip-compressed copy of the WebAssembly-compatible
Arabic-script model from `tesseract-ocr/tessdata_fast`, pinned to commit
`9f875fb8194767ea22a0018072ba8d3ebf3939cc`.

Source: `script/Arabic.traineddata`

The filename is changed locally so Tesseract.js can load it as the `sorani`
language choice. The upstream Apache 2.0 license is compatible with Tesseract.

Compressed SHA-256: `1eb72390e7e958534d8a94337cb427673ab95ac6eb7dbd714a328453ddb75c5c`
