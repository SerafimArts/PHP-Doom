#version 450
#extension GL_EXT_samplerless_texture_functions : require

layout(set = 0, binding = 1) uniform texture2D atlas;
layout(set = 0, binding = 2) uniform texture2D palette;

layout(location = 0) in vec2 inPixel;
layout(location = 1) flat in uint inShift;

layout(location = 0) out vec4 outColor;

void main()
{
    // Indexed color: the atlas keeps palette indices, the palette turns them into colors.
    int index = int(texelFetch(atlas, ivec2(inPixel), 0).r * 255.0 + 0.5);

    outColor = texelFetch(palette, ivec2(int((uint(index) + inShift) & 255u), 0), 0);
}
