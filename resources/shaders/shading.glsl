// What the fragment shaders need to turn a texel of the atlas into a lit color.

#extension GL_EXT_samplerless_texture_functions : require

layout(set = 0, binding = 4) uniform texture2D atlas;
layout(set = 0, binding = 5) uniform texture2D colormap;
layout(set = 0, binding = 6) uniform texture2D palettes;

// The color of a picture at a texel: x is the palette index (0..255), y is 1.0 when the texel is opaque. The texel
// coordinates wrap around the picture.
vec2 fetchPicture(uint picture, ivec2 texel)
{
    uvec4 rect = rects[picture];
    ivec2 size = ivec2(rect.zw);
    ivec2 wrapped = ((texel % size) + size) % size;

    vec2 value = texelFetch(atlas, ivec2(rect.xy) + wrapped, 0).rg;

    return vec2(floor(value.r * 255.0 + 0.5), value.g);
}

// The start row of the colormap for a light level: the brighter the sector, the lower the row.
int lightStart(uint lightLevel, int bias)
{
    int level = clamp(int(lightLevel >> 4) + bias + int(frame.view.z), 0, 15);

    return (15 - level) * 4;
}

// Row of the colormap for a wall at the given depth.
int wallRow(int start, float depth)
{
    int scale = min(47, int(2560.0 / max(depth, 1.0)));

    return clamp(start - scale / 2, 0, COLORMAP_LAST_ROW);
}

// Row of the colormap for a floor or a ceiling at the given depth.
int flatRow(int start, float depth)
{
    int step = clamp(int(depth / 16.0) + 1, 1, 128);

    return clamp(start - 80 / step, 0, COLORMAP_LAST_ROW);
}

vec3 shade(float index, int row)
{
    int lit = int(texelFetch(colormap, ivec2(int(index), row), 0).r * 255.0 + 0.5);

    return texelFetch(palettes, ivec2(lit, int(frame.parameters.x)), 0).rgb;
}
