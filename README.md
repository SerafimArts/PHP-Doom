```
              =================     ===============     ===============   ================
              \\ . . . . . . .\\   //. . . . . . .\\   //. . . . . . .\\  \\. . .\\// . .//
              ||. . ._____. . .|| ||. . ._____. . .|| ||. . ._____. . .|| || . . .\/ . ..||
              || . .||   ||. . || || . .||   ||. . || || . .||   ||. . || ||. . . . . . .||
              ||. . ||   || . .|| ||. . ||   || . .|| ||. . ||   || . .|| || . | . . . ..||
              || . .||   ||. _-|| ||-_ .||   ||. . || || . .||   ||. _-|| ||-_.|\ . . . .||
              ||. . ||   ||-'  || ||  `-||   || . .|| ||. . ||   ||-'  || ||  `|\_ . .|..||
              || . _||   ||    || ||    ||   ||_ . || || . _||   ||    || ||   |\ `-_/| .||
              ||_-' ||  .|/    || ||    \|.  || `-_|| ||_-' ||  .|/    || ||   | \  / -_.||
              ||    ||_-'      || ||      `-_||    || ||    ||_-'      || ||   | \  / | '||
              ||    `'         || ||         `'    || ||    `'         || ||   | \  / |  ||
              ||            .===' `===.         .==='.`===.         .===' /==. |  \/  |  ||
              ||         .=='   \_|-_ `===. .==='   _|_   `===. .===' _-|/   `==  \/  |  ||
              ||      .=='    _-'    `-_  `='    _-'   `-_    `='  _-'   `-_  /|  \/  |  ||
              ||   .=='    _-'          `-__\._-'         `-_./__-'         `' |. /|  |  ||
              ||.=='    _-'                                                     `' | /==.||
              =='    _-'                   P         H         P                    \/  `==
              \   _-'                                                                `-_  /
               `''                                                                      ``'
```

[![YouTube Video](https://img.youtube.com/vi/4zbtD8bmw7E/maxresdefault.jpg)](https://www.youtube.com/watch?v=4zbtD8bmw7E)

Short YouTube Video: [https://www.youtube.com/watch?v=4zbtD8bmw7E](https://www.youtube.com/watch?v=4zbtD8bmw7E)

# Why?

Just... This was bound to happen eventually +)

## Requirements

- PHP 8.4+
- ext-ffi
- Vulkan-compatible GPU

On macOS GLFW and the Vulkan loader are taken from Homebrew (MoltenVK has no ray queries, so the ray traced lighting
is not available there):

```bash
brew install glfw molten-vk vulkan-loader
```

## Usage

Just run `php bin/doom --help`
