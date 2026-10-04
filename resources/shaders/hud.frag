#version 450
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "shading.glsl"

layout(location = 0) in vec2 inTexel;
layout(location = 1) flat in uint inPicture;
layout(location = 2) flat in int inRow;
layout(location = 3) flat in uvec2 inSize;

layout(location = 0) out vec4 outColor;

void main()
{
    ivec2 texel = clamp(ivec2(floor(inTexel)), ivec2(0), ivec2(inSize) - 1);
    vec2 picture = fetchPicture(inPicture, texel);

    if (picture.y < 0.5)
    {
        discard;
    }

    outColor = vec4(shade(picture.x, inRow), 1.0);
}
