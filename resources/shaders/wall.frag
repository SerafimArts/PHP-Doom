#version 460
#ifdef RAY_SHADOWS
#extension GL_EXT_ray_query : require
#endif
#extension GL_GOOGLE_include_directive : require

#include "level.glsl"
#include "shading.glsl"
#include "shadow.glsl"

layout(location = 0) in vec2 inUv;
layout(location = 1) flat in uint inPicture;
layout(location = 2) flat in uint inLight;
layout(location = 3) flat in uint inFlags;
layout(location = 4) in vec3 inWorld;
layout(location = 5) flat in vec3 inNormal;

layout(location = 0) out vec4 outColor;

void main()
{
    ivec2 texel = ivec2(floor(inUv));

    // A masked texture inside an opening is drawn once, it does not repeat upwards or downwards.
    if ((inFlags & WALL_MASKED) != 0u && (texel.y < 0 || texel.y >= int(rects[inPicture].w)))
    {
        discard;
    }

    vec2 picture = fetchPicture(inPicture, texel);

    if (picture.y < 0.5)
    {
        discard;
    }

    float depth = 1.0 / gl_FragCoord.w;

    vec3 glow = lightSurface(inWorld, inNormal, MASK_SURFACE);
    vec3 color = shade(picture.x, wallRow(lightStart(inLight, lightBoost(glow)), depth));

    outColor = vec4(applyLight(color, glow), 1.0);
}
