// Data of a level that the vertex and the fragment shaders share. Bindings are listed in Render\LevelRenderer.

#include "limits.glsl"

const uint NO_SECTOR = 0xFFFFFFFFu;

const uint SECTOR_FLOOR_IS_SKY = 1u;
const uint SECTOR_CEILING_IS_SKY = 2u;

const uint WALL_UNPEGGED = 1u;
const uint WALL_SCROLLING = 2u;
const uint WALL_MASKED = 4u;

const uint WALL_UPPER = 0u;
const uint WALL_LOWER = 1u;
const uint WALL_MIDDLE = 2u;

layout(std140, set = 0, binding = 0) uniform Frame
{
    mat4 viewProjection;
    // xyz: the eye, w: seconds since the start
    vec4 eye;
    // x: palette, y: width of the canvas, z: height of the canvas, w: the picture of the sky
    uvec4 parameters;
    // x: direction of the view in radians, y: tangent of half of the horizontal field of view, z: light of the muzzle
    // flash in steps of the colormap
    vec4 view;
} frame;

struct Sector
{
    float floorHeight;
    float ceilingHeight;
    uint light;
    uint floorPicture;
    uint ceilingPicture;
    uint flags;
    uint padding0;
    uint padding1;
};

layout(std430, set = 0, binding = 1) readonly buffer Sectors
{
    Sector sectors[];
};

// x, y, width, height of every picture in the atlas
layout(std430, set = 0, binding = 2) readonly buffer Rects
{
    uvec4 rects[];
};
